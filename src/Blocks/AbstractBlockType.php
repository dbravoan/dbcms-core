<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Blocks\Contracts\TranslatableBlockInterface;
use Illuminate\Support\Str;

abstract class AbstractBlockType implements BlockTypeInterface, TranslatableBlockInterface
{
    /** @var array<string,string> */
    private const LABELS = [
        'title' => 'Título',
        'content' => 'Contenido',
        'description' => 'Descripción',
        'caption' => 'Leyenda',
        'subtitle' => 'Subtítulo',
        'summary' => 'Resumen',
        'body' => 'Contenido',
        'quote' => 'Cita',
        'question' => 'Pregunta',
        'answer' => 'Respuesta',
        'cta_text' => 'Texto del botón',
        'cta_url' => 'Destino del botón',
        'secondary_cta_text' => 'Texto del botón secundario',
        'secondary_cta_url' => 'Destino del botón secundario',
        'image_url' => 'Imagen',
        'after_image_url' => 'Imagen «después»',
        'image_alt' => 'Descripción accesible de la imagen',
        'video_url' => 'Vídeo',
        'mobile_video_url' => 'Vídeo para móvil',
        'poster_url' => 'Póster del vídeo',
        'bg_image_url' => 'Imagen de fondo',
        'bg_video_url' => 'Vídeo de fondo',
        'bg_video_poster' => 'Póster del vídeo de fondo',
        'avatar' => 'Avatar',
        'logo_url' => 'Logotipo',
        'grid_columns' => 'Columnas',
        'limit' => 'Límite de elementos',
        'height' => 'Altura',
        'interval' => 'Intervalo entre diapositivas',
        'autoplay' => 'Reproducción automática',
        'show_arrows' => 'Mostrar flechas',
        'show_dots' => 'Mostrar indicadores',
        'controls' => 'Mostrar controles',
        'loop' => 'Repetir al terminar',
        'muted' => 'Silenciar',
        'format' => 'Formato',
        'icon' => 'Icono',
        'name' => 'Nombre',
        'price' => 'Precio',
        'period' => 'Periodo o descripción',
        'features' => 'Características',
        'role' => 'Cargo',
        'author' => 'Autor',
        'rating' => 'Valoración',
        'badge' => 'Insignia',
        'before_label' => 'Etiqueta «antes»',
        'after_label' => 'Etiqueta «después»',
        'placeholder' => 'Texto de ayuda',
        'size' => 'Tamaño',
        'selection' => 'Selección',
        'category' => 'Categoría',
        'post_id' => 'Artículo',
        'product_id' => 'Producto',
        'form_slug' => 'Formulario',
        'button_text' => 'Texto del botón',
        'album_slug' => 'Galería',
        'currency_symbol' => 'Símbolo de moneda',
        'initial_investment' => 'Inversión inicial',
        'annual_savings' => 'Ahorro anual',
        'project_years' => 'Años del proyecto',
        'current_solution_cost' => 'Coste actual',
        'optimized_solution_cost' => 'Coste optimizado',
        'payback_months' => 'Meses de amortización',
        'chart_type' => 'Tipo de gráfica',
        'donut_percentage' => 'Porcentaje del anillo',
        'donut_label' => 'Etiqueta del anillo',
        'donut_subtext' => 'Texto inferior del anillo',
        'metric_value' => 'Valor de la métrica',
        'metric_label' => 'Etiqueta de la métrica',
        'metric_description' => 'Descripción de la métrica',
        'unit' => 'Unidad',
        'color' => 'Color',
        'label' => 'Etiqueta',
        'value' => 'Valor',
        'url' => 'URL',
        'source_url' => 'Fuente original',
        'source_label' => 'Texto de la fuente',
        'original_url' => 'Archivo original',
        'after_original_url' => 'Archivo original «después»',
        'original_label' => 'Texto del archivo original',
        'media_width' => 'Anchura reservada del medio',
        'media_height' => 'Altura reservada del medio',
        'include_form' => 'Mostrar solicitud de revisión',
    ];

    /** @var array<string,string> */
    private const DESCRIPTIONS = [
        'image_url' => 'Selecciona o pega la URL de la imagen. Si el bloque también tiene vídeo, esta imagen puede usarse como póster.',
        'after_image_url' => 'Imagen que se muestra al mover el comparador hacia el lado «después».',
        'video_url' => 'Archivo de vídeo o URL directa. Para un vídeo de YouTube usa el campo específico del bloque.',
        'mobile_video_url' => 'Versión opcional del vídeo para pantallas pequeñas.',
        'poster_url' => 'Imagen que se muestra antes de reproducir el vídeo.',
        'image_alt' => 'Describe la imagen para lectores de pantalla. Si se deja vacío se usa el título del bloque.',
        'cta_url' => 'Ruta interna, por ejemplo /contacto, o una URL externa segura.',
        'secondary_cta_url' => 'Ruta interna o URL externa segura del botón secundario.',
        'grid_columns' => 'Número de columnas que se usan en pantallas grandes.',
        'limit' => 'Número máximo de elementos que se mostrarán.',
        'height' => 'Altura del bloque en píxeles.',
        'interval' => 'Milisegundos entre diapositivas cuando la reproducción automática está activa.',
        'autoplay' => 'Avanza automáticamente entre las diapositivas.',
        'controls' => 'Muestra los controles nativos del reproductor.',
        'loop' => 'Vuelve a empezar cuando termina el vídeo.',
        'muted' => 'Reproduce sin sonido; es necesario para el autoplay en la mayoría de navegadores.',
        'format' => 'Markdown es más sencillo de escribir; HTML se sanea antes de publicarse.',
        'icon' => 'Emoji, símbolo o clase de icono que se muestra en la tarjeta.',
        'source_url' => 'Página original de la fuente o del archivo.',
        'source_label' => 'Texto visible del enlace a la fuente.',
        'media_width' => 'Dimensión de reserva para evitar saltos de diseño. Usa 0 para calcularla automáticamente.',
        'media_height' => 'Dimensión de reserva para evitar saltos de diseño. Usa 0 para calcularla automáticamente.',
        'include_form' => 'Muestra el formulario para solicitar una revisión del estudio.',
    ];

    /** @var list<string> */
    private const TEXTAREA_KEYS = [
        'content',
        'description',
        'caption',
        'subtitle',
        'summary',
        'body',
        'quote',
        'answer',
        'transcript',
        'notes',
        'guidance',
        'assumptions',
        'session_definition',
        'disclaimer',
        'metric_description',
    ];

    public function getRequiredModule(): ?string
    {
        return null;
    }

    public function isFullWidth(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return '';
    }

    public function getEditorSchema(): array
    {
        return $this->schemaFor($this->getDefaultPayload());
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,array<string,mixed>>
     */
    private function schemaFor(array $payload): array
    {
        $fields = [];
        foreach ($payload as $key => $value) {
            $mediaKind = $this->mediaKind($key);
            $field = [
                'type' => match (true) {
                    is_array($value) && array_is_list($value) && is_array($value[0] ?? null) => 'repeater',
                    is_array($value) && array_is_list($value) => 'list',
                    is_array($value) => 'json',
                    is_bool($value) => 'boolean',
                    is_int($value), is_float($value) => 'number',
                    $mediaKind !== null => 'media',
                    $key === 'url' || str_ends_with($key, '_url') => 'url',
                    in_array($key, self::TEXTAREA_KEYS, true) || str_contains($key, 'content') || str_contains($key, 'description') => 'textarea',
                    default => 'text',
                },
                'label' => self::LABELS[$key] ?? Str::headline($key),
            ];

            if (isset(self::DESCRIPTIONS[$key])) {
                $field['description'] = self::DESCRIPTIONS[$key];
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $field['default'] = $value;
            }
            if ($field['type'] === 'media') {
                $field['media_kind'] = $mediaKind ?? 'image';
                $altKey = $this->altKey($key);
                if ($altKey !== null && array_key_exists($altKey, $payload)) {
                    $field['alt_key'] = $altKey;
                }
            }
            if ($field['type'] === 'repeater') {
                $field['fields'] = $this->schemaFor($value[0]);
            }
            $fields[$key] = $field;
        }

        return $fields;
    }

    private function mediaKind(string $key): ?string
    {
        $key = strtolower($key);
        if (preg_match('/(?:^|_)(?:video|movie|clip)(?:_url)?$/D', $key) === 1) {
            return 'video';
        }
        if (preg_match('/(?:^|_)(?:image|photo|avatar|logo|poster|thumbnail|cover)(?:_url)?$/D', $key) === 1) {
            return 'image';
        }

        return null;
    }

    private function altKey(string $key): ?string
    {
        return match ($key) {
            'image_url' => 'image_alt',
            'after_image_url' => 'after_image_alt',
            default => null,
        };
    }

    public function getEditorView(): ?string
    {
        return null;
    }

    public function getEditorData(): array
    {
        return [];
    }

    public function getTranslationPaths(): array
    {
        return [];
    }
}
