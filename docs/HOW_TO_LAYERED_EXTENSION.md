# Extensión por capas

Proceso canónico para crecer un sitio dbCMS **sin modificar Core en el
proyecto**. El núcleo se consume por Composer; el proyecto **extiende** con
paquetes y contenido editable. No se parchea `vendor/`, no se usan repositorios
`path` hacia Core u oficiales en una web de producto, y no se inventa un CMS
paralelo en Blade o JSON.

Contratos: [Arquitectura](CORE_AND_EXTENSIONS.md) ·
[Extensiones](EXTENSIONS.md) · [Convenciones](CONVENTIONS.md). El arnés de
agente en webs nuevas es el skill `dbcms-capas` del instalador.

## Core intocable (regla de sistema)

En una aplicación dbCMS:

1. Trata `vendor/dbravoan/dbcms-core` y el resto de paquetes oficiales de
   Packagist como **solo lectura**.
2. No edites, no copies a `App` para “arreglar”, no forks locales de Core ni de
   temas/bloques oficiales en el día a día del sitio.
3. Si el contrato no alcanza, **documenta el caso** en el repositorio oficial de
   Core (o del paquete dueño), publica la corrección y **actualiza la
   dependencia** con Composer.
4. Los repositorios Composer `path` hacia Core u oficiales solo valen en una
   aplicación **desechable** para probar un candidato; no en el proyecto de
   producto. [Publicación](PUBLICATION.md)

Extender ≠ modificar el sistema. Extender es añadir módulos, temas o paquetes de
bloques del proyecto (o de Packagist) y componer contenido en el CMS.

## Capas y dueños

| Capa | Dueño | Qué vive ahí |
| --- | --- | --- |
| Sistema | Core y paquetes oficiales (Packagist) | Instalación, páginas, secciones, medios, formularios, privacidad, MCP, bloques nativos, temas admin oficiales |
| Proyecto (código) | Paquetes `dbcms-theme`, `dbcms-module`, `dbcms-block-package` de la web | Presentación de marca, negocio propio, bloques que Core no aporta |
| Proyecto (glue) | `App`, `routes/`, overrides puntuales en `resources/` | Registro mínimo, rutas de aplicación, overrides documentados del contrato |
| Editorial | Base de datos vía panel / MCP | Textos, medios, menús, SEO, composiciones por secciones |

Los paquetes adhoc de un solo cliente (p. ej. un área Members) son válidos como
`dbcms-module` del proyecto. No son plantilla del starter ni parte de Core.

## Árbol de decisión

1. ¿Lo debe poder cambiar un editor sin despliegue?
   → Contenido en el CMS: páginas por **secciones y bloques**, medios, menús,
   formularios, SEO de página. Usa el constructor o MCP.
   [Composición](EXTENSIONS.md#composición-y-payload-de-bloques) ·
   [Landings](HOW_TO_RICH_TEXT_LANDINGS.md)
2. ¿Es solo presentación (layout, tokens, cabecera, CSS/JS de marca)?
   → Tema `dbcms-theme` del proyecto. Cada tema aporta su **layout propio**; no
   hay fallback implícito entre temas ni reescritura de namespaces del padre.
   Engancha layouts de marca con `appearance.html_class` + CSS del tema.
   [Vistas y assets](EXTENSIONS.md#vistas-temas-y-assets) ·
   [Clases HTML del tema](HOW_TO_THEME_HTML_CLASSES.md)
3. ¿Es negocio, API, CRUD, hosts o datos propios?
   → Módulo `dbcms-module` del proyecto (o uno oficial ya publicado).
4. ¿Falta un tipo visual reutilizable y no existe en Core ni en un paquete
   instalado?
   → Paquete `dbcms-block-package` (del proyecto o a publicar).
5. ¿Falta un contrato compartido entre muchas webs?
   → Cambio en el repositorio propietario de Core (u oficial), nunca un parche
   en el sitio.

Preferencia de contenido: **secciones + bloques**. Las plantillas `blade:` con
campos registrados son el contrato de Core cuando el layout lo exige; no
sustituyen al constructor con copy hardcodeado en vistas.

## Anti-patrones

- Editar o sustituir Core / oficiales dentro del proyecto de producto.
- Marketing, FAQs, catálogos o sedes en Blade, JSON de repo o `config/` cuando
  deberían ser páginas, bloques o datos del módulo dueño.
- Tema que “hereda” otro reescribiendo `View::addNamespace` o overrides del tema
  base como si hubiera fallback entre temas.
- CSS/JS de marca fuera del pipeline Vite del tema o de los paquetes instalados.
- Segundo CMS (scripts que generan el sitio al margen del panel) como fuente de
  verdad del contenido.
- Módulo nuevo que duplica una entidad que ya posee otro paquete del mismo
  proyecto (p. ej. un directorio paralelo si las sedes salen del CRM del módulo
  Members).

## Verificación

- Contenido: editable y publicable desde el backoffice (o MCP + revisión).
- Código: vive en el paquete dueño; Core en `vendor/` sin cambios locales.
- Disponibilidad: módulo o paquete de bloques instalable/activable desde el CMS.
- Dependencias oficiales: solo versiones de Packagist (o tag publicado) en el
  lock del producto.
