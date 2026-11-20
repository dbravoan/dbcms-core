<?php

declare(strict_types=1);

use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use Illuminate\Support\Str;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the notifications centre is scoped per user (plus global ones)', function (): void {
    $other = User::factory()->create();

    foreach ([
        ['Global', null],
        ['Mine', $this->admin->id],
        ['Other', $other->id],
    ] as [$title, $userId]) {
        SystemNotification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'module' => 'test',
            'type' => 'success',
            'title' => $title,
            'message' => 'msg',
            'is_read' => false,
        ]);
    }

    $response = $this->actingAs($this->admin, 'web')->getJson(route('admin.notifications.index'));

    $response->assertOk();
    $titles = array_column($response->json('notifications'), 'title');

    expect($titles)->toContain('Global', 'Mine')
        ->and($titles)->not->toContain('Other')
        ->and($response->json('unread_count'))->toBe(2);
});
