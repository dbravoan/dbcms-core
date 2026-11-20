<?php

declare(strict_types=1);

use DbCms\Core\Services\Install\ProjectReadme;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/dbcms-project-readme-'.bin2hex(random_bytes(8));
    mkdir($this->directory, 0700);
    $this->path = $this->directory.'/README.md';
    $this->pending = "<!-- dbcms:site:pending -->\nLa identidad del sitio se añadirá aquí al completar la instalación.\n<!-- /dbcms:site -->";
});

afterEach(function (): void {
    foreach ([$this->path, $this->directory.'/other.md'] as $path) {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        }
    }
    rmdir($this->directory);
});

it('updates only the generated block and preserves the project file mode and newline convention', function (): void {
    $body = str_replace("\n", "\r\n", "# Project\n\n".$this->pending."\n\nTexto propio.\n");
    file_put_contents($this->path, $body);
    chmod($this->path, 0640);
    $readme = new ProjectReadme;
    expect($readme->complete($this->path, 'Estudio Ágil', "[Norte]\n& Sur"))->toBeTrue();
    $result = file_get_contents($this->path);
    expect($result)->toBe("# Project\r\n\r\n<!-- dbcms:site:configured -->\r\n## Identidad del sitio\r\n\r\n- **Nombre del sitio** — Estudio Ágil\r\n- **Titular** — &#91;Norte&#93; &#38; Sur\r\n<!-- /dbcms:site -->\r\n\r\nTexto propio.\r\n")
        ->and(fileperms($this->path) & 0777)->toBe(0640);
    expect($readme->complete($this->path, 'Otro entorno', 'Otro titular'))->toBeTrue()
        ->and(file_get_contents($this->path))->toBe($result);
});

it('preserves a missing file, a customized marker and a symbolic link', function (): void {
    $readme = new ProjectReadme;
    expect($readme->complete($this->path, 'Sitio', 'Titular'))->toBeTrue()
        ->and(file_exists($this->path))->toBeFalse();
    $custom = str_replace('La identidad del sitio', 'Texto personalizado', $this->pending);
    file_put_contents($this->path, $custom);
    expect($readme->complete($this->path, 'Sitio', 'Titular'))->toBeTrue()
        ->and(file_get_contents($this->path))->toBe($custom);

    $target = $this->directory.'/other.md';
    file_put_contents($target, $this->pending);
    unlink($this->path);
    symlink($target, $this->path);
    expect($readme->complete($this->path, 'Sitio', 'Titular'))->toBeTrue()
        ->and(is_link($this->path))->toBeTrue()
        ->and(file_get_contents($target))->toBe($this->pending);
});
