<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Closure;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Sections\SectionAppearance;
use DbCms\Core\Services\Content\SafeContentRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** One definition owns the editor, validation and rendering of each block. */
final class BlockRegistry implements BlockRegistryInterface
{
    /** @var array<string,BlockTypeInterface> */
    private array $blocks = [];

    /** @var array<string,string> */
    private array $packages = [];

    /** @var array<string,bool>|null */
    private ?array $renderPackages = null;

    /** @var array<string,bool> */
    private array $renderModules = [];

    public function __construct(private readonly ModuleManager $modules, private readonly BlockPackageManager $packageManager) {}

    public function register(BlockTypeInterface $block, ?string $package = null): void
    {
        $type = $block->getType();
        if (! preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $type)) {
            throw new \DomainException('Invalid block type identifier.');
        }
        if (isset($this->blocks[$type])) {
            throw new \LogicException('Duplicate block definition: '.$type);
        }
        $this->blocks[$type] = $block;
        if ($package !== null) {
            $this->packages[$type] = $package;
        }
    }

    public function get(string $type): ?BlockTypeInterface
    {
        return $this->blocks[$type] ?? null;
    }

    public function all(): array
    {
        return $this->blocks;
    }

    public function isAvailable(string $type): bool
    {
        $block = $this->blocks[$type] ?? null;
        if ($block === null || (isset($this->packages[$type]) && ! ($this->renderPackages !== null ? ($this->renderPackages[$this->packages[$type]] ?? false) : $this->packageManager->isEnabled($this->packages[$type])))) {
            return false;
        }
        $module = $block->getRequiredModule();

        return $module === null || $this->moduleAvailable($module);
    }

    public function getActiveBlocks(): array
    {
        $packages = $this->renderPackages ?? $this->packageManager->availability();
        $modules = [];

        return array_filter($this->blocks, function (BlockTypeInterface $block) use ($packages, &$modules): bool {
            $type = $block->getType();
            if (isset($this->packages[$type]) && ! ($packages[$this->packages[$type]] ?? false)) {
                return false;
            }
            $module = $block->getRequiredModule();
            if ($module === null) {
                return true;
            }

            return $modules[$module] ??= $this->moduleAvailable($module);
        });
    }

    /** A read-only render operation shares availability; the next root always re-reads it.
     * @template TResult
     *
     * @param Closure():TResult $render
     * @return TResult */
    public function withinAvailability(Closure $render): mixed
    {
        if ($this->renderPackages !== null) {
            return $render();
        }
        $this->renderPackages = $this->packageManager->availability();
        try {
            return $render();
        } finally {
            $this->invalidateAvailability();
        }
    }

    /** Availability mutation events may also invalidate an operation in progress. */
    public function invalidateAvailability(): void
    {
        $this->renderPackages = null;
        $this->renderModules = [];
    }

    private function moduleAvailable(string $module): bool
    {
        if ($this->renderPackages === null) {
            return $this->modules->isModuleEnabled($module);
        }

        return $this->renderModules[$module] ??= $this->modules->isModuleEnabled($module);
    }

    public function getDynamicDataPayloads(): array
    {
        $data = [];
        foreach ($this->getActiveBlocks() as $block) {
            $data = array_replace($data, $block->getEditorData());
        }

        return $data;
    }

    public function render(string $type, array $payload, ?Market $market = null, ?string $locale = null): string
    {
        if (! $this->isAvailable($type)) {
            return '';
        }

        if (! app(BlockTree::class)->supports(['type' => $type] + $payload)) {
            Log::warning('Formato de bloque retirado; requiere conversión explícita.', ['type' => $type, 'node_id' => $payload['id'] ?? null]);

            return '';
        }
        $payload = $this->safeUrlPayload($payload, $this->blocks[$type]->getEditorSchema());

        return $this->blocks[$type]->render($payload, $market, $locale);
    }

    public function validate(string $type, array $payload): void
    {
        $this->withinAvailability(function () use ($type, $payload): void {
            $this->validatePayload($type, $payload);
        });
    }

    /** @param array<string,mixed> $payload */
    private function validatePayload(string $type, array $payload): void
    {
        if (! $this->isAvailable($type)) {
            throw ValidationException::withMessages(['sections_json' => 'This block is unavailable: '.$type]);
        }
        $tree = app(BlockTree::class);
        $tree->assertLimits($payload);
        foreach ($tree->nodes(array_replace($payload, ['type' => $type])) as $node) {
            $nodeType = (string) ($node['type'] ?? 'text');
            if (! $this->isAvailable($nodeType)) {
                continue; // The shared writer reconciles stored unavailable instances first.
            }
            if (! $tree->supports($node)) {
                throw ValidationException::withMessages(['sections_json' => 'Formato retirado de '.$nodeType.'. Conserva el contenido y solicita una conversión explícita.']);
            }
            Validator::make($node, $this->validationRules($this->blocks[$nodeType]->getEditorSchema()) + [
                'bindings' => ['nullable', 'array'],
                'bindings.*' => ['array:source,field,id'],
                'bindings.*.source' => ['required', 'string', 'max:100'],
                'bindings.*.field' => ['required', 'string', 'max:100'],
                'bindings.*.id' => ['nullable', 'integer', 'min:1'],
            ])->validate();
            SectionAppearance::validate($node);
            BlockStyle::validate((array) ($node['appearance'] ?? []));
        }
    }

    /**
     * @param  array<string,array<string,mixed>>  $schema
     * @return array<string,mixed>
     */
    private function validationRules(array $schema, string $prefix = ''): array
    {
        $rules = [];
        foreach ($schema as $key => $field) {
            $path = $prefix.$key;
            $rules[$path] = $field['rules'] ?? match ($field['type'] ?? 'text') {
                'number' => ['nullable', 'numeric'],
                'integer' => ['nullable', 'integer'],
                'select' => ['nullable', Rule::in(array_is_list((array) ($field['options'] ?? [])) ? (array) ($field['options'] ?? []) : array_keys((array) ($field['options'] ?? [])))],
                'boolean' => ['nullable', 'boolean'],
                'json', 'array', 'list', 'repeater', 'object', 'blocks' => ['nullable', 'array'],
                'media' => ['nullable', 'string'],
                default => ['nullable', 'string'],
            };
            if (in_array($field['type'] ?? null, ['url', 'media'], true)) {
                $isMedia = ($field['type'] ?? null) === 'media';
                $fieldRules = is_string($rules[$path]) ? explode('|', $rules[$path]) : (array) $rules[$path];
                $rules[$path] = [...$fieldRules, static function (string $attribute, mixed $value, Closure $fail) use ($isMedia): void {
                    if (! is_string($value) || ! (new SafeContentRenderer)->isSafeUrl($value, $isMedia)) {
                        $fail('This field requires a safe local or external URL.');
                    }
                }];
            }
            if (($field['required'] ?? false) === true) {
                $fieldRules = is_string($rules[$path]) ? explode('|', $rules[$path]) : (array) $rules[$path];
                $rules[$path] = ['required', ...$fieldRules];
            }
            if (($field['type'] ?? null) === 'repeater') {
                $rules[$path.'.*'] = ['array'];
                $rules = array_replace($rules, $this->validationRules((array) ($field['fields'] ?? []), $path.'.*.'));
            }
            if (($field['type'] ?? null) === 'object') {
                $rules = array_replace($rules, $this->validationRules((array) ($field['fields'] ?? []), $path.'.'));
            }
        }

        return $rules;
    }

    /**
     * The same URL policy applies to existing stored content before rendering.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    private function safeUrlPayload(array $payload, array $schema): array
    {
        foreach ($schema as $key => $field) {
            if (in_array($field['type'] ?? null, ['url', 'media'], true) && array_key_exists($key, $payload)) {
                if (! is_string($payload[$key]) || ! (new SafeContentRenderer)->isSafeUrl($payload[$key], ($field['type'] ?? null) === 'media')) {
                    $payload[$key] = '';
                }
            }
            if (($field['type'] ?? null) === 'repeater' && is_array($payload[$key] ?? null)) {
                foreach ($payload[$key] as $index => $item) {
                    if (is_array($item)) {
                        $payload[$key][$index] = $this->safeUrlPayload($item, (array) ($field['fields'] ?? []));
                    }
                }
            }
        }

        return $payload;
    }
}
