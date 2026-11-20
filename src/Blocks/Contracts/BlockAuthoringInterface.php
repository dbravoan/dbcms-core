<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

/** Presets are authored payloads discoverable by editors and automated clients. */
interface BlockAuthoringInterface
{
    /**
     * @return array<string, array{label:string, description:string, media:list<string>, payload:array<string,mixed>}>
     */
    public function presets(string $locale): array;
}
