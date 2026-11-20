<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use DbCms\Core\Capabilities\Contracts\CapabilityPresentationInterface;
use DbCms\Core\Capabilities\DTOs\CapabilityAvailability;
use DbCms\Core\Capabilities\DTOs\CapabilityDescription;
use DbCms\Core\Modules\ModuleManager;

final class AvailableCapabilities
{
    public function __construct(private readonly CapabilityRegistry $registry, private readonly CapabilityAuthorizer $authorizer, private readonly ModuleManager $modules, private readonly CapabilityPresentationInterface $presentation) {}

    /**
     * @param  array<string,mixed>  $args
     */
    public function reason(string $name, ActorContext $actor, array $args = []): ?string
    {
        $capability = $this->registry->get($name);
        if ($capability === null) {
            return 'Capacidad desconocida.';
        }
        $meta = $this->registry->metadata($name);
        if ($meta['module'] !== null && ! $this->modules->isModuleEnabled($meta['module'])) {
            return 'Módulo no disponible.';
        }
        if (! $this->authorizer->allowed($actor, $capability->ability(), $capability->permissionFor($args))) {
            return 'Permisos insuficientes o identidad revocada.';
        }
        $reason = $this->presentation->reason($name, $args, $actor);
        if ($reason !== null) {
            return $reason;
        }

        return $meta['availability'] !== null ? ($meta['availability'])($actor, $args) : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function manifest(ActorContext $actor): array
    {
        $manifest = [];
        foreach ($this->registry->all() as $name => $capability) {
            if ($this->reason($name, $actor) !== null) {
                continue;
            }
            $meta = $this->registry->metadata($name);
            $entry = (new CapabilityDescription($name, $capability->description(), ['type' => 'object', 'properties' => $capability->properties(), 'required' => $capability->required()], $meta['effect'], $meta['module'], $meta['dependencies']))->toArray();
            $entry = $this->presentation->schema($name, $entry, $actor);
            if ($entry !== null) {
                $manifest[] = $entry;
            }
        }

        return $manifest;
    }

    /** @param array<string,mixed> $args */
    public function availability(string $name, ActorContext $actor, array $args = []): CapabilityAvailability
    {
        $reason = $this->reason($name, $actor, $args);

        return new CapabilityAvailability($reason === null, $reason);
    }

    /** @return list<array{name:string,reason:string}> */
    public function unavailable(ActorContext $actor): array
    {
        $items = [];
        foreach ($this->registry->all() as $name => $capability) {
            if (! $this->authorizer->allowed($actor, $capability->ability(), $capability->permissionFor([]))) {
                continue;
            }
            $reason = $this->reason($name, $actor);
            if ($reason !== null) {
                $items[] = ['name' => $name, 'reason' => $reason];
            }
        }

        return $items;
    }
}
