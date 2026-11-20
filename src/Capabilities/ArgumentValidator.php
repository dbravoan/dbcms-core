<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use DbCms\Core\Capabilities\Contracts\CapabilityInterface;
use Illuminate\Validation\ValidationException;

final class ArgumentValidator
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function validate(CapabilityInterface $capability, array $args, bool $planning = false): void
    {
        foreach ($capability->required() as $key) {
            if (! array_key_exists($key, $args)) {
                throw ValidationException::withMessages([$key => 'Campo requerido.']);
            }
        }
        foreach ($args as $key => $value) {
            $schema = $capability->properties()[$key] ?? null;
            if ($schema !== null) {
                $this->check($schema, $value, $key, $planning);
            } elseif ($planning) {
                throw ValidationException::withMessages([$key => 'Argumento no definido por la capacidad.']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function check(array $schema, mixed $value, string $path, bool $planning = false): void
    {
        if ($planning && is_array($value) && count($value) === 1 && isset($value['$ref'])) {
            return;
        }
        $types = (array) ($schema['type'] ?? []);
        $valid = $types === [];
        foreach ($types as $type) {
            $valid = $valid || match ($type) {
                'string' => is_string($value), 'integer' => is_int($value), 'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value), 'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || ! array_is_list($value)), 'null' => $value === null,
                default => false,
            };
        }
        if (! $valid || (isset($schema['enum']) && ! in_array($value, (array) $schema['enum'], true))) {
            throw ValidationException::withMessages([$path => 'Tipo o valor inválido.']);
        }
        if (is_string($value) && ((isset($schema['minLength']) && strlen($value) < $schema['minLength']) || (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']))) {
            throw ValidationException::withMessages([$path => 'Longitud inválida.']);
        }
        if (is_numeric($value) && ((isset($schema['minimum']) && $value < $schema['minimum']) || (isset($schema['maximum']) && $value > $schema['maximum']))) {
            throw ValidationException::withMessages([$path => 'Valor fuera de límites.']);
        }
        if (is_array($value)) {
            foreach ((array) ($schema['required'] ?? []) as $required) {
                if (! array_key_exists((string) $required, $value)) {
                    throw ValidationException::withMessages([$path.'.'.$required => 'Campo requerido.']);
                }
            }
            foreach ($value as $key => $item) {
                $child = $schema['properties'][$key] ?? ($schema['items'] ?? null);
                if (is_array($child)) {
                    $this->check($child, $item, $path.'.'.$key, $planning);
                }
            }
        }
    }
}
