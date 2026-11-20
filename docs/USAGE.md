# Usar dbCMS Core

Completa `/install` y entra al panel. Con la ruta de administración predeterminada, el acceso es `/admin/login`. Las opciones visibles dependen de los permisos de la cuenta. [Instalación paso a paso](https://github.com/dbravoan/dbcms-installer/blob/main/docs/HOW_TO_START.md)

## Crear y publicar contenido

1. En **Páginas**, crea un borrador, elige los mercados y completa título, slug y metadatos de cada idioma. Usa el constructor para maquetar con secciones o selecciona una vista Blade registrada para editar sus campos.
2. Elige las columnas de cada sección y añade bloques del catálogo. Usa **Rich Text** para texto con formato y **Plantillas** para insertar una copia editable de una composición. Consulta **Vista previa** en escritorio y móvil.
3. Guarda y sigue el flujo de publicación del sitio. Comprueba la URL pública en el idioma y mercado elegidos. La vista previa de un borrador permite revisar el contenido sin publicarlo. [Landings con Rich Text](HOW_TO_RICH_TEXT_LANDINGS.md)

En **Plantillas** se guardan composiciones reutilizables. En **Multimedia** se gestionan los archivos que el constructor y los formularios de edición reutilizan. En **Menús y zonas** se ordenan enlaces y submenús, se definen sus traducciones y se asigna el menú a su ubicación pública.

## Configurar el sitio

| Para gestionar… | Usa el panel así |
| --- | --- |
| Idiomas y mercados | En **Mercados**, configura los idiomas activos y el idioma principal de cada mercado. Revisa slugs, enlaces y contenido en cada combinación que publiques. |
| Temas y extensiones | Usa **Plantillas y temas** para consultar la presentación. En **Módulos del sistema**, instala y activa los módulos que Composer aporta. En **Paquetes de bloques**, habilita el paquete visual que necesites. |
| Cuentas y accesos | Gestiona cuentas y roles desde **Usuarios y roles**. Usa los permisos del recurso para dar acceso a funciones del panel. |
| Formularios y envíos | En **Formularios**, define campos, traducciones y acciones. Inserta el formulario con el bloque `form` y consulta las respuestas en **Envíos**. El formulario de contacto de la instalación se identifica como `contact`. |
| Privacidad | En **Ajustes del sitio**, revisa los datos del titular y los tratamientos que aplica la web. Comprueba aviso legal, privacidad y cookies en cada idioma habilitado. |
| SEO y búsqueda | Completa los metadatos por traducción. Usa **Auditoría SEO y GEO** para revisar contenido si tu cuenta tiene acceso. Ajusta la búsqueda pública en **General Search** y comprueba el resultado en el mercado activo. |
| Antispam y mantenimiento | Abre **Security & Dynamic Captcha** o **Mantenimiento & Construcción** en **Sistema y ajustes**. Comprueba el formulario o una página pública con la configuración efectiva. |

## Conectar MCP

En **Sistema y ajustes → Servidor MCP**, consulta el endpoint y emite un token Passport asociado a una cuenta con los permisos de la tarea. Descubre las operaciones disponibles con `cms_capabilities`, los campos con `cms_entity_types` y los bloques con `cms_block_types`. Usa el identificador y la versión que devuelve el servidor para editar nodos concretos. En una solicitud de publicación, abre `review_url` con una cuenta autorizada. [Conexión MCP y ejemplo de Blog](https://github.com/dbravoan/dbcms-installer/blob/main/docs/HOW_TO_MCP.md)

## Operar mediante Artisan

Ejecuta los comandos en el entorno PHP de la aplicación. Con Sail, usa `./vendor/bin/sail artisan`. Consulta los argumentos con `artisan help <comando>`.

| Tarea | Comando |
| --- | --- |
| Sincronizar el catálogo de permisos | `cms:access:sync` |
| Emitir credenciales de integración | `cms:issue-mcp-token` para un usuario, `cms:create-m2m-client` para una cuenta de servicio |
| Incorporar archivos de `public/uploads` a la biblioteca | `cms:media:reconcile` |
| Reconstruir el índice de búsqueda | `search:reindex` |
| Aplicar la conservación de datos del sitio | `privacy:prune` |
| Atender solicitudes sobre datos de cuentas, formularios y ejecuciones | `accounts:privacy`, `forms:privacy`, `executions:privacy` |

Para automatización y desarrollo, consulta [arquitectura](CORE_AND_EXTENSIONS.md) y [contratos de extensiones](EXTENSIONS.md).
