<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use Illuminate\Validation\ValidationException;

/** Accepts Lottie data, never executable expressions or uncontrolled asset fetches. */
final class AnimationDocument
{
    public function validate(string $json): void
    {
        if (strlen($json) > (int) config('cms.blocks.max_animation_bytes', 5242880)) {
            $this->invalid();
        }
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->invalid();
        }
        if (! is_array($data) || ! is_array($data['layers'] ?? null) || ! is_numeric($data['fr'] ?? null)
            || (float) $data['fr'] <= 0 || (float) $data['fr'] > 240 || ! is_numeric($data['ip'] ?? null)
            || ! is_numeric($data['op'] ?? null) || (float) $data['op'] < (float) $data['ip']) {
            $this->invalid();
        }
        $objects = 0;
        $scan = function (array $value) use (&$scan, &$objects): void {
            if (++$objects > 50000 || (isset($value['x']) && is_string($value['x']) && $value['x'] !== '') || ! empty($value['fPath'])) {
                $this->invalid();
            }
            if (isset($value['p']) && is_string($value['p'])) {
                $path = (string) ($value['u'] ?? '').$value['p'];
                if (! preg_match('~^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$~D', $path)) {
                    $this->invalid(); // Embedded images only; no delayed third-party or relative fetches.
                }
            }
            foreach ($value as $item) {
                if (is_array($item)) {
                    $scan($item);
                }
            }
        };
        $scan($data);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['file' => 'La animación debe ser un documento Lottie válido, acotado y sin expresiones ni recursos externos.']);
    }
}
