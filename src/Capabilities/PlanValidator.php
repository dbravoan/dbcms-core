<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use DbCms\Core\Capabilities\DTOs\ExecutionPlan;
use DbCms\Core\Capabilities\DTOs\ExecutionStep;
use InvalidArgumentException;

final class PlanValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $available
     */
    public function validate(array $data, array $available): ExecutionPlan
    {
        $rows = $data['steps'] ?? [];
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 20) {
            throw new InvalidArgumentException('El plan debe contener como máximo 20 pasos ordenados.');
        }
        $steps = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Paso inválido.');
            }
            $id = $row['id'] ?? null;
            $capability = $row['capability'] ?? null;
            $args = $row['arguments'] ?? [];
            $dependencies = $row['depends_on'] ?? [];
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $id) || isset($seen[$id])) {
                throw new InvalidArgumentException('Identificador de paso inválido o repetido.');
            }
            if (! is_string($capability) || ! in_array($capability, $available, true)) {
                throw new InvalidArgumentException('El plan utiliza una capacidad no disponible.');
            }
            if (! is_array($args) || ! is_array($dependencies) || ! array_is_list($dependencies)) {
                throw new InvalidArgumentException('Argumentos o dependencias inválidos.');
            }
            foreach ($dependencies as $dependency) {
                if (! is_string($dependency) || ! isset($seen[$dependency])) {
                    throw new InvalidArgumentException('Las dependencias deben referirse a pasos anteriores.');
                }
            }
            $this->checkArguments($args, $dependencies);
            $steps[] = new ExecutionStep($id, $capability, $args, $dependencies);
            $seen[$id] = true;
        }
        $question = isset($data['question']) && is_string($data['question']) && trim($data['question']) !== '' ? $data['question'] : null;
        if ($steps === [] && $question === null) {
            throw new InvalidArgumentException('El plan debe contener pasos o solicitar información.');
        }
        if ($steps !== [] && $question !== null) {
            throw new InvalidArgumentException('Solicita primero la aclaración, sin ejecutar un plan ambiguo.');
        }

        return new ExecutionPlan($steps, is_string($data['summary'] ?? null) ? $data['summary'] : '', $question);
    }

    /**
     * @param  array<array-key, mixed>  $args
     * @param  list<string>  $dependencies
     */
    private function checkArguments(array $args, array $dependencies): void
    {
        foreach ($args as $key => $value) {
            if (in_array($key, ['confirm', 'approval_id', 'actor_id', 'user_id', 'token_id', 'is_m2m', 'scopes'], true)) {
                throw new InvalidArgumentException('El modelo no puede aportar identidad, permisos ni aprobaciones.');
            }
            if ($key === '$ref') {
                if (count($args) !== 1 || ! is_string($value) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*(?:\.[a-zA-Z0-9_-]+)*$/D', $value) || ! in_array(explode('.', $value, 2)[0], $dependencies, true)) {
                    throw new InvalidArgumentException('Referencia de resultado inválida.');
                }
            } elseif (is_array($value)) {
                $this->checkArguments($value, $dependencies);
            }
        }
    }
}
