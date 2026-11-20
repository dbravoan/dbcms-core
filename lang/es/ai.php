<?php

declare(strict_types=1);

return ['editor_text_title' => 'Asistente de texto',
    'image_title' => 'Generar imagen', 'video_title' => 'Generar vídeo',
    'image_help' => 'Describe la imagen que necesitas y revisa el resultado antes de asignarlo a este campo.',
    'video_help' => 'Describe el vídeo que necesitas y elige un formato y una duración admitidos por tu proveedor.',
    'translation' => [
        'title' => 'Traducir con IA',
        'help' => 'Traduce los textos de este elemento y sus bloques conservando imágenes, enlaces y estructura. Los idiomas corresponden a sus mercados asignados.',
        'source' => 'Idioma de origen', 'target' => 'Idioma de destino', 'choose' => 'Selecciona un idioma',
        'generate' => 'Generar traducción', 'working' => 'Preparando traducción…', 'apply' => 'Aplicar traducción',
        'preview' => 'Revisa la propuesta antes de aplicarla. Después deberás guardar el formulario para conservar los cambios.',
        'replace' => 'He revisado la propuesta y quiero reemplazar los textos existentes del idioma de destino.',
        'slug' => 'La dirección del idioma de destino se conserva. Si está vacía, complétala antes de guardar.',
        'stale' => 'El contenido o los mercados han cambiado. Genera una nueva traducción para incluir esos cambios.',
        'invalid' => 'Selecciona dos idiomas distintos de los mercados asignados a este elemento.',
        'empty' => 'Completa el contenido de origen y asigna al menos un mercado antes de traducir.',
        'failed' => 'No se ha podido generar la traducción. Revisa la conexión y los permisos.',
        'timeout' => 'La traducción continúa en segundo plano. Consulta la tarea antes de volver a generarla.',
        'protected' => 'Estos bloques se conservan sin traducir porque su módulo no declara campos traducibles:',
    ]];
