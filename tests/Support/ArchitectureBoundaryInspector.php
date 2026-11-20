<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/** Deterministic source checks, including aliases and nested statements. No app boot. */
final class ArchitectureBoundaryInspector
{
    /** @param list<string> $forbidden
     * @return list<string>
     */
    public function dependencyViolations(string $source, array $forbidden): array
    {
        $nodes = $this->parse($source);
        $finder = new NodeFinder;
        $violations = [];
        foreach ($finder->find($nodes, static fn (Node $node): bool => $node instanceof Name || $node instanceof Node\Scalar\String_) as $node) {
            $value = $node instanceof Name ? $node->toString() : $node->value;
            foreach ($forbidden as $prefix) {
                if ($value === $prefix || str_starts_with($value, $prefix.'\\')) {
                    $violations[] = $value;
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    public function controllerViolations(string $source): array
    {
        $nodes = $this->parse($source);
        $finder = new NodeFinder;
        $errors = [];
        $routeTypes = ['DbCms\\Core\\Models\\User', 'DbCms\\Core\\Models\\Market', 'DbCms\\Core\\Models\\Language'];
        $serviceParameters = [];
        $classes = $finder->findInstanceOf($nodes, Stmt\Class_::class);
        foreach ($classes as $class) {
            $invoke = $class->getMethod('__invoke');
            if ($invoke === null) {
                $errors[] = 'missing_invokable_action';

                continue;
            }
            foreach ($class->getMethods() as $method) {
                if ($method->isPublic() && ! in_array($method->name->toString(), ['__construct', '__invoke'], true)) {
                    $errors[] = 'multiple_public_actions';
                }
            }
            foreach ($invoke->params as $parameter) {
                $type = $parameter->type instanceof Node\NullableType ? $parameter->type->type : $parameter->type;
                if ($type instanceof Name && interface_exists($type->toString()) && $parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                    $serviceParameters[] = $parameter->var->name;
                }
                if ($type instanceof Name && str_contains($type->toString(), '\\Models\\')) {
                    $routeTypes[] = $type->toString();
                }
            }
        }
        foreach ($finder->findInstanceOf($nodes, Name::class) as $name) {
            $value = $name->toString();
            // User may represent the authenticated actor; ORM operations on it are still prohibited below.
            if ((str_starts_with($value, 'DbCms\\Core\\Models\\') || (str_starts_with($value, 'DbCms\\Modules\\') && str_contains($value, '\\Models\\'))) && ! in_array($value, $routeTypes, true)) {
                $errors[] = 'model_dependency';
            }
        }
        foreach ($finder->find($nodes, fn (Node $node): bool => $node instanceof Stmt\Foreach_ || $node instanceof Stmt\For_ || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_ || $node instanceof Stmt\Switch_) as $loop) {
            $errors[] = 'business_loop';
        }
        foreach ($finder->findInstanceOf($nodes, Expr\StaticCall::class) as $call) {
            $class = $call->class instanceof Name ? $call->class->toString() : '';
            if (str_starts_with($class, 'DbCms\\Core\\Models\\') || str_contains($class, '\\Models\\') || in_array($class, ['PDO', 'Illuminate\\Support\\Facades\\DB', 'Illuminate\\Support\\Facades\\File', 'Illuminate\\Support\\Facades\\Artisan', 'Illuminate\\Support\\Facades\\Hash', 'Illuminate\\Support\\Facades\\Mail', 'Illuminate\\Support\\Facades\\Http'], true)) {
                $errors[] = 'persistence_or_external_effect';
            }
        }
        foreach ($finder->findInstanceOf($nodes, Expr\New_::class) as $creation) {
            if ($creation->class instanceof Name && ($creation->class->toString() === 'PDO' || str_contains($creation->class->toString(), '\\Models\\'))) {
                $errors[] = 'persistence_or_external_effect';
            }
        }
        foreach ($finder->findInstanceOf($nodes, Expr\MethodCall::class) as $call) {
            $delegates = ($call->var instanceof Expr\PropertyFetch && $call->var->var instanceof Expr\Variable && $call->var->var->name === 'this')
                || ($call->var instanceof Expr\Variable && in_array($call->var->name, $serviceParameters, true));
            if (! $delegates && $call->name instanceof Node\Identifier && in_array($call->name->toString(), ['save', 'delete', 'fill', 'updateOrCreate', 'firstOrCreate', 'query', 'where', 'insert'], true)) {
                $errors[] = 'persistence_or_external_effect';
            }
        }
        foreach ($finder->findInstanceOf($nodes, Expr\Eval_::class) as $evaluation) {
            $errors[] = 'executable_code';
        }
        foreach ($finder->findInstanceOf($nodes, Expr\FuncCall::class) as $call) {
            $contextLookup = false;
            if ($call->name instanceof Name && $call->name->toString() === 'app') {
                $argument = $call->args[0]->value ?? null;
                $contextLookup = $argument === null || ($argument instanceof Node\Scalar\String_ && str_starts_with($argument->value, 'current.'));
            }
            if (! $contextLookup && $call->name instanceof Name && in_array($call->name->toString(), ['app', 'resolve', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open'], true)) {
                $errors[] = 'service_locator_or_process';
            }
        }

        return array_values(array_unique($errors));
    }

    /** @return list<string> */
    public function serviceViolations(string $source): array
    {
        $nodes = $this->parse($source);
        $finder = new NodeFinder;
        $errors = [];
        foreach ($finder->findInstanceOf($nodes, Name::class) as $name) {
            $value = $name->toString();
            if (str_starts_with($value, 'Illuminate\\Http\\') || str_starts_with($value, 'Symfony\\Component\\HttpFoundation\\') || str_starts_with($value, 'DbCms\\Core\\Http\\')) {
                $errors[] = 'http_dependency';
            }
        }

        return array_values(array_unique($errors));
    }

    /** Detect direct remote I/O; domain-callback dependencies still need review and functional tests.
     * @return list<string>
     */
    public function transactionViolations(string $source): array
    {
        $nodes = $this->parse($source);
        $errors = [];
        $walk = function (Node $node, bool $inTransaction = false, bool $deferred = false) use (&$walk, &$errors): void {
            if ($node instanceof Expr\StaticCall && $node->class instanceof Name && $node->name instanceof Node\Identifier) {
                $class = $node->class->toString();
                $method = $node->name->toString();
                if ($inTransaction && ! $deferred && in_array($class, ['Illuminate\\Support\\Facades\\Http', 'Illuminate\\Support\\Facades\\Mail', 'GuzzleHttp\\Client'], true)) {
                    $errors[] = 'external_effect_inside_transaction';
                }
                if ($class === 'Illuminate\\Support\\Facades\\DB' && $method === 'transaction') {
                    $inTransaction = true;
                }
                if ($class === 'Illuminate\\Support\\Facades\\DB' && $method === 'afterCommit') {
                    $deferred = true;
                }
            }
            foreach ($node->getSubNodeNames() as $name) {
                $children = $node->$name;
                foreach (is_array($children) ? $children : [$children] as $child) {
                    if ($child instanceof Node) {
                        $walk($child, $inTransaction, $deferred);
                    }
                }
            }
        };
        foreach ($nodes as $node) {
            $walk($node);
        }

        return array_values(array_unique($errors));
    }

    /** Constructor cycles within the supplied ownership scope (not deferred container callbacks).
     * @param  list<string>  $sources
     * @return list<string>
     */
    public function constructorCycles(array $sources): array
    {
        $classes = [];
        $bindings = [];
        $finder = new NodeFinder;
        foreach ($sources as $source) {
            foreach ($finder->findInstanceOf($this->parse($source), Stmt\Class_::class) as $class) {
                $name = $class->namespacedName?->toString() ?? $class->name?->toString();
                if ($name === null) {
                    continue;
                }
                $classes[$name] = $class;
                foreach ($class->implements as $interface) {
                    $bindings[$interface->toString()] = $name;
                }
            }
        }
        $edges = [];
        foreach ($classes as $name => $class) {
            $edges[$name] = [];
            $constructor = $class->getMethod('__construct');
            foreach ($constructor->params ?? [] as $parameter) {
                if ($parameter->type instanceof Name) {
                    $dependency = $bindings[$parameter->type->toString()] ?? $parameter->type->toString();
                    if (isset($classes[$dependency])) {
                        $edges[$name][] = $dependency;
                    }
                }
            }
        }
        $cycles = [];
        $walk = function (string $name, array $path) use (&$walk, &$cycles, $edges): void {
            if (in_array($name, $path, true)) {
                $cycles[] = implode(' -> ', [...$path, $name]);

                return;
            }
            foreach ($edges[$name] ?? [] as $dependency) {
                $walk($dependency, [...$path, $name]);
            }
        };
        foreach (array_keys($edges) as $name) {
            $walk($name, []);
        }

        return array_values(array_unique($cycles));
    }

    /** @return array<Node> */
    private function parse(string $source): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
        $resolver = new NodeTraverser(new NameResolver);

        return $resolver->traverse($nodes);
    }
}
