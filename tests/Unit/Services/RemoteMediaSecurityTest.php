<?php

declare(strict_types=1);

use DbCms\Core\Services\Media\MediaManagerService;
use DbCms\Core\Services\Media\RemoteMediaUrlPolicy;

test('remote media policy rejects private and ambiguous destinations', function (string $url): void {
    expect(fn () => (new RemoteMediaUrlPolicy)->resolve($url))->toThrow(InvalidArgumentException::class);
})->with(['http://127.0.0.1/a.png', 'https://[::1]/a.png', 'http://169.254.169.254/latest/meta-data/', 'https://user:pass@example.com/a.png', 'file:///etc/passwd', 'https://2130706433/a.png', 'https://100.64.0.1/a.png', 'https://[2001::a00:1]/a.png', 'https://[2002:7f00:1::]/a.png']);

test('media binary import refuses executable extensions before writing files', function (): void {
    expect(fn () => app(MediaManagerService::class)->saveFromBinary('<?php echo 1;', 'php', 'unsafe'))->toThrow(InvalidArgumentException::class);
});
