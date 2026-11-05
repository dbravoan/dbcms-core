<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Closure;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Content\SafeContentRenderer;
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
        if ($block === null || (isset($this->packages[$type]) && ! $this->packageManager->isEnabled($this->packages[$type]))) {
            return false;
        }
        $module = $block->getRequiredModule();

        return $module === null || $this->modules->isModuleEnabled($module);
    }

    public function getActiveBlocks(): array
    {
        $packages = $this->packageManager->availability();
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

            return $modules[$module] ??= $this->modules->isModuleEnabled($module);
        });
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

        $payload = $this->safeUrlPayload($payload, $this->blocks[$type]->getEditorSchema());

        return $this->blocks[$type]->render($payload, $market, $locale);
    }

    public function validate(string $type, array $payload): void
    {
        if (! $this->isAvailable($type)) {
            throw ValidationException::withMessages(['sections_json' => 'This block is unavailable: '.$type]);
        }
        Validator::make($payload, $this->validationRules($this->blocks[$type]->getEditorSchema()))->validate();
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
                'json', 'array', 'list', 'repeater' => ['nullable', 'array'],
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
