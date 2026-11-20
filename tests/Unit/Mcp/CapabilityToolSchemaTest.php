<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use DbCms\Core\Mcp\Native\Tools\CapabilityTool;
use PHPUnit\Framework\TestCase;

final class CapabilityToolSchemaTest extends TestCase
{
    public function test_native_tool_preserves_the_complete_capability_schema(): void
    {
        $schema = ['type' => 'object', 'required' => ['sections'], 'properties' => [
            'sections' => ['type' => ['array', 'object'], 'description' => 'Sections by locale'],
            'sections_json' => ['type' => ['object', 'string']],
            'market_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'minItems' => 1],
            'translations' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'required' => ['title'], 'properties' => ['title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255]]]],
        ]];
        $tool = new CapabilityTool('cms_schema_fixture', 'Fixture', $schema);

        self::assertSame($schema, $tool->toArray()['inputSchema']);
    }

    public function test_parameterless_tool_serializes_properties_as_an_object(): void
    {
        $tool = new CapabilityTool('cms_empty_fixture', 'Fixture', ['type' => 'object', 'properties' => [], 'required' => []]);

        self::assertSame('{"type":"object","properties":{},"required":[]}', json_encode($tool->toArray()['inputSchema']));
    }
}
