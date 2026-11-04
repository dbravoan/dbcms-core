<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

final class EnvValueSerializer
{
    public static function encode(string $value): string
    {
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new \InvalidArgumentException('Environment values must not contain newlines or NUL bytes.');
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
