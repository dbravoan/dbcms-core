# dbCMS Core

Núcleo reutilizable para aplicaciones Laravel 13 y PHP 8.4. Incluye páginas,
idiomas y mercados, edición por bloques, medios, permisos, publicación, SEO,
privacidad, formularios, antispam, búsqueda, mantenimiento y transporte MCP.
Core incluye bloques nativos y la marca de administración `dbcms`.

## Crear una web

```bash
composer global require dbravoan/dbcms-installer:~0.0.1
dbcms new mi-web
```

El instalador crea Laravel, instala core y el tema `default-clean`, permite elegir
extensiones y compila los recursos. Para abrir `/install`, introduce el código
de un solo uso guardado como `CMS_INSTALL_TOKEN` en el archivo `.env` del
servidor. Si pierdes la sesión, genera un código nuevo con `openssl rand -hex 32`,
actualiza ese valor y vuelve al asistente. Usa HTTPS si accedes en remoto.
Cada proyecto conserva sus dependencias y lockfiles; el despliegue le pertenece.

## Integración en Laravel limpio

Instala core y un tema mediante Composer. `php artisan cms:setup` prepara los
archivos de integración, sin acceder a la base de datos. El asistente web o
`php artisan cms:install` realizan después el aprovisionamiento explícito.
Consulta [desarrollo](docs/DEVELOPMENT.md) para ejecutar herramientas en Docker/Sail.

## Usar dbCMS

- [Administrar contenidos y configurar el sitio](docs/USAGE.md).
- [Crear landings con Rich Text desde el panel y MCP](docs/HOW_TO_RICH_TEXT_LANDINGS.md).
- [Crear y desplegar una web con Installer](https://github.com/dbravoan/dbcms-installer).

## Programar y extender

- [Arquitectura y responsabilidades](docs/CORE_AND_EXTENSIONS.md).
- [Código limpio y reutilización](docs/CONVENTIONS.md).
- [Contratos de módulos, temas, bloques y Blade](docs/EXTENSIONS.md).
- [Desarrollo](docs/DEVELOPMENT.md), [pruebas](docs/TESTING.md), [publicación](docs/PUBLICATION.md) y [contribución](CONTRIBUTING.md).

Licencia MIT.
