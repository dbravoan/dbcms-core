# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [Sin publicar]

## [1.2.0] - 2026-11-13

### Añadido

- Configuración final del instalador en cuatro pasos, con titular particular, NIF/CIF y datos registrales o DPO condicionales; conserva los datos al navegar y valida antes del envío final.

- Árboles de bloques declarados por esquema, con límites, apariencia tipada, render compartido con Blade y extracción semántica anidada.
- Editor recursivo, historial de sesión, previsualización sin envíos, datos y ejemplo Blade.
- Inspector contextual de contenido, diseño y opciones avanzadas con vista de la composición, controles visuales y herencia responsive.
- Región de contenido compartida con Blade, extensión a ventana explícita y motor común de fondos y movimiento para secciones, columnas y bloques.
- Plantillas insertables, componentes reutilizables publicados, fuentes públicas y vinculaciones acotadas.
- Catálogo resumido, referencias paginadas, `cms:blocks:describe` y edición de nodos con versión.
- Assets públicos por demanda, overlays accesibles y audio/Lottie validados en medios.
- Vite incorpora los estilos de paquetes desde la entrada del proyecto, tanto con dependencias enlazadas como distribuidas.


## [1.1.0] - 2026-11-12

### Añadido

- `cms:access:sync` y `AccessProvisioner` para crear/reconciliar permisos y roles.
- Gates generados desde el catálogo de permisos, incluidos módulos opcionales.
- Plantillas legales y de privacidad para `fr`, `it`, `pt`, `de`, `ar` y `zh-CN`.
- `MoneyFormatter` con CLDR para cualquier moneda ISO 4217.
- Helper `public_translation` y `PublicTranslation::resolveForMarketId` para
  compartir la resolución de idioma y mercado en vistas, modelos y servicios.

### Corregido

- Tokens y roles ya no permiten escalada desde `manage:users`.
- Traducciones públicas sin fallback a otro idioma.
- Ajustes vaciados que recuperaban el valor por defecto.
- Resolución de layouts por página y rutas de módulos desactivados.

## [1.0.0] - 2026-11-05

### Añadido

- Primera versión pública.
- `dbravoan/dbcms-core` — El núcleo aporta instalador web, identidad/permisos, idiomas y mercados, medios, páginas, menús, publicación, secciones, temas, SEO técnico y privacidad. El código propio de Laravel permanece en `App`; el núcleo…
