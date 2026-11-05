# dbravoan/dbcms-core 1.0.0

## Qué hace

El núcleo aporta instalador web, identidad/permisos, idiomas y mercados, medios,
páginas, menús, publicación, secciones, temas, SEO técnico y privacidad. El código
propio de Laravel permanece en `App`; el núcleo utiliza `DbCms\Core`.

En una instalación nueva abre `/install`, indica datos reales de base de datos y
responsable/empresa y crea el administrador. El sitio comienza vacío. Configura
idiomas y mercados antes de crear contenido: traducir no publica ni asigna a todos
los mercados.

Crea una Page, selecciona idioma/mercados y secciones o una plantilla Blade
registrada. Guarda el borrador y publica mediante el flujo de revisión aplicable.
Verifica la URL pública anónima en su contexto. Desactivar el propietario de un
bloque impide su ejecución, conservando sus instancias.

Medios y menús conservan sus permisos. El pie legal y consentimiento usan la
misma información configurada de empresa/tratamientos; revísala al cambiar
integraciones. Los layouts completos personalizados incluyen la UI de privacidad.

## Requisitos y dependencias

Según `packages/core/composer.json` (Laravel y ecosistema PHP del monorepo).
Identificador de producto: núcleo `dbravoan/dbcms-core`.

## Instalación y activación

El núcleo se instala con Composer en el proyecto/workbench. Completa `/install`
o `cms:install`. No es un módulo activable del catálogo de módulos.

## Configuración

Claves de entorno / `config/cms.php` por nombre (sin valores), entre otras:

- `CMS_BRAND` → `cms.admin_theme`
- `CMS_THEME` → `cms.theme`
- ajuste persistido `cms.active_theme`
- `APP_NAME` → `cms.name`
- `ADMIN_PATH` → `cms.admin_path`
- `CMS_INSTALLATION_ID` → `cms.installation_id`
- `CMS_SEED_DEMO`, `CMS_DEMO_SEEDER`
- `CMS_PAGE_CACHE`, `CMS_PAGE_CACHE_TTL`
- `CMS_QUICK_LOGIN`
- `CMS_ADMINER_URL`
- `CMS_PUBLIC_WRITES_PER_MINUTE`
- `CMS_ADMIN_LOCALES`
- `CMS_LOCK_ROLES_IN_PRODUCTION`
- `CMS_MODULES`
- Fallbacks de integraciones: `GA4_MEASUREMENT_ID`, `ADSENSE_CLIENT_ID`, `META_PIXEL_ID`, `META_CAPI_TOKEN`, `CMS_MAINTENANCE_MODE`

## Rutas

Instalador `/install`, panel bajo `ADMIN_PATH`, rutas públicas de páginas/medios
del núcleo. El detalle vive en los ServiceProviders y `routes` del paquete.

## Comandos Artisan

Definidos en `packages/core/src/Console/Commands/`:

- `cms:install`
- `cms:issue-mcp-token` — usado por el módulo MCP; **pertenece al núcleo**
- `cms:create-m2m-client`
- `cms:cache:purge`
- `cms:maintenance`
- `executions:privacy` — usado con Orchestration; **pertenece al núcleo**
- `accounts:privacy`
- `privacy:configure`
- `privacy:prune`
- `media:migrate-to-external`
- `search:reindex`

## Permisos

Identidad y permisos del núcleo (`access:admin`, gestión de páginas/medios/menús/SEO/módulos, etc.). El catálogo concreto se registra en migraciones y políticas del núcleo.

## Privacidad y datos

Empresa y tratamientos `legal.processing`, pie legal, consentimiento y comandos
`privacy:*` / `accounts:privacy` / `executions:privacy`. Consulta el contrato de extensiones.

## Enlaces

- [Extensiones](../../docs/EXTENSIONS.md)
- [Núcleo y extensiones](../../specs/CORE_AND_EXTENSIONS.md)
- [README del monorepo](../../README.md)
