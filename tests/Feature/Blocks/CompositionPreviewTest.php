<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    $this->withoutVite();
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
    $this->composition = [
        'type' => 'accordion', 'id' => 'preview-composition',
        'items' => [['title' => 'Panel', 'children' => [['type' => 'heading', 'id' => 'preview-title', 'text' => 'Composición privada']]]],
    ];
    $this->previewInput = ['type' => 'accordion', 'payload' => $this->composition, 'entity_type' => 'page', 'locale' => 'es', 'market' => 'es'];
});

it('renders a selected composition in its real themed region without writing editorial data', function (): void {
    $response = $this->postJson(route('admin.blocks.preview'), $this->previewInput + ['selected_node_id' => 'preview-title']);
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $html = $response->json('html');
    expect($html)->toContain('data-cms-preview', 'front-fixture-theme', 'data-cms-content-region', 'data-preview-node="preview-title"', 'Composición privada', 'data-cms-node="preview-title"');
    expect(Page::query()->count())->toBe(0)
        ->and(DB::table('page_sections')->count())->toBe(0);
});

it('rejects a selection outside declared composition slots rather than inspecting arbitrary payload data', function (): void {
    $input = $this->previewInput;
    $input['payload']['metadata'] = ['type' => 'heading', 'id' => 'not-a-child', 'text' => 'Not a structural slot'];
    $this->postJson(route('admin.blocks.preview'), $input + ['selected_node_id' => 'not-a-child'])->assertUnprocessable()->assertJsonValidationErrors('selected_node_id');
    $this->postJson(route('admin.blocks.preview'), $this->previewInput + ['selected_node_id' => 'other-root'])->assertUnprocessable()->assertJsonValidationErrors('selected_node_id');
    $this->postJson(route('admin.blocks.preview'), $this->previewInput + ['selected_column_index' => 9])->assertUnprocessable()->assertJsonValidationErrors('selected_column_index');
});

it('previews every submitted section when the toolbar asks for the full composition', function (): void {
    $input = [
        'entity_type' => 'page',
        'locale' => 'es',
        'market' => 'es',
        'sections' => [
            ['type' => 'heading', 'payload' => ['id' => 'sec-a', 'type' => 'heading', 'text' => 'Primera sección', 'level' => 'h1']],
            ['type' => 'rich_text', 'payload' => ['id' => 'sec-b', 'type' => 'rich_text', 'content' => 'Segunda sección', 'format' => 'markdown']],
        ],
    ];
    $html = $this->postJson(route('admin.blocks.preview'), $input)->assertOk()->json('html');
    expect($html)->toContain('Primera sección', 'Segunda sección', 'data-cms-preview');
});

it('previews canonical columns and their styles through the same section renderer as the website', function (): void {
    $input = $this->previewInput;
    $input['payload'] = ['type' => 'drawer', 'id' => 'column-composition', 'title' => 'Panel', 'columns_layout' => '2', 'columns' => [
        ['id' => 'left', 'style' => ['bg_type' => 'color', 'bg_color' => '#123456'], 'blocks' => [['type' => 'heading', 'id' => 'left-title', 'text' => 'Columna izquierda']]],
        ['id' => 'right', 'blocks' => [['type' => 'heading', 'id' => 'right-title', 'text' => 'Columna derecha']]],
    ]];
    $response = $this->postJson(route('admin.blocks.preview'), $input + ['selected_column_index' => 0]);
    $response->assertOk();
    expect($response->json('html'))->toContain('data-preview-column="0"', 'data-cms-column-index="0"', '#123456', 'Columna izquierda', 'Columna derecha');
    $rootResponse = $this->postJson(route('admin.blocks.preview'), $input + ['selected_node_id' => 'column-composition']);
    $rootResponse->assertOk();
    expect($rootResponse->json('html'))->toContain('data-preview-node="column-composition"', 'data-cms-node="column-composition"');
});

it('enforces editorial authorization before returning any composition preview', function (): void {
    $this->actingAs(User::factory()->create());
    $this->postJson(route('admin.blocks.preview'), $this->previewInput)->assertForbidden();
});

it('rejects oversized selection identifiers before rendering a preview', function (): void {
    $this->postJson(route('admin.blocks.preview'), $this->previewInput + ['selected_node_id' => str_repeat('x', 129)])->assertUnprocessable()->assertJsonValidationErrors('selected_node_id');
    $this->postJson(route('admin.blocks.preview'), $this->previewInput + ['selected_column_index' => -1])->assertUnprocessable()->assertJsonValidationErrors('selected_column_index');
});
