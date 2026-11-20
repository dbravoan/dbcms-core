# Changelog

## 0.0.1

- Editor Rich Text compartido para bloques y campos traducibles de páginas Blade. El catálogo MCP expone los campos de cada plantilla. Véase [Páginas Blade](docs/EXTENSIONS.md#páginas-blade).
- `rich_text` identifica el bloque de texto enriquecido del constructor. [Composición](docs/EXTENSIONS.md#composición-y-payload-de-bloques)
- El catálogo MCP ofrece presets y edición por ID en colecciones que declara el esquema. [Autoría de bloques](docs/EXTENSIONS.md#composición-y-payload-de-bloques)
- La navegación pública compartida renderiza menús anidados y conserva el contexto de idioma y mercado.
- La gestión de idiomas y mercados conserva el código canónico de los idiomas con traducciones legales incluidas, como `zh-CN`.

- Tags independientes por paquete para la distribución con Composer.

- La vista previa editorial aísla la sesión y usa los recursos públicos compilados.

- Fuente de desarrollo y publicación independiente.
- Admin predeterminado y 28 bloques generales integrados en core.
- Integración de aplicaciones Laravel limpias mediante `cms:setup`.
- Aplicación de pruebas basada en Laravel oficial, fixtures públicos y calidad propia.
- Ciclos independientes de Core, módulos y temas.
