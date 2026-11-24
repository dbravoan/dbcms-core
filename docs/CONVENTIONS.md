# Convenciones de desarrollo

Aplica esta guía a Core, a las extensiones y al código de la aplicación. La [arquitectura](CORE_AND_EXTENSIONS.md) identifica el dueño de cada responsabilidad. El [contrato de extensiones](EXTENSIONS.md) define los puntos de integración. En un sitio consumidor, Core y los paquetes oficiales son solo lectura: [extensión por capas](HOW_TO_LAYERED_EXTENSION.md).

## Buscar y reutilizar antes de implementar

1. Identifica la entrada real del cambio. Sigue la ruta, comando, herramienta MCP o componente hasta el servicio propietario y sus pruebas. Para contribuciones dinámicas, revisa el proveedor, `extra.dbcms`, `module.json` y el registro correspondiente.
2. Busca una función, vista o patrón equivalente en el propietario y en Core. En una aplicación instalada, inspecciona contratos y código bajo `vendor/dbravoan/dbcms-core/` y los paquetes de `composer.lock`. Usa esos archivos como referencia de la versión que ejecuta la web.
3. Reutiliza el punto de extensión y sus validaciones. Si falta una operación común, amplía el dueño existente y comprueba sus consumidores. Conserva las reglas de autorización, publicación, localización y privacidad en un único servicio.
4. Verifica el cambio desde la entrada normal. En la entrega indica qué reutilizas y qué comportamiento compruebas.

| Si necesitas… | Empieza por… |
| --- | --- |
| Formularios, tablas, filtros, diálogos o acciones del panel | `resources/views/components/admin/`, `resources/css/admin-ui.css` y una pantalla equivalente. Usa las clases `admin-*`, las etiquetas accesibles y los tokens de marca. [Kit administrativo](EXTENSIONS.md#ui-del-backoffice-dbcms-y-termocms) |
| Texto enriquecido y selección de medios | `<x-admin.rich-text-editor>`, `<x-admin.media-field>`, el selector compartido y `MediaUrlResolver`. [Catálogo y medios](EXTENSIONS.md#catálogo-para-asistentes-y-medios) |
| Guardar contenido, recursos o secciones | `EntityMutationService`, `ResourceCrudService`, `SectionSyncService`, el esquema y el handler del propietario. Los DTO y Form Requests expresan la entrada del canal. [Mutaciones](CORE_AND_EXTENSIONS.md#mutaciones-y-verificación) |
| Resolver contenido público por idioma y mercado | El lector del propietario, `PublicContentDiscovery`, `PublicTranslation` y `PublicSemanticRegistry`. Busca un caso equivalente en las páginas o Blog. [Contenido público](EXTENSIONS.md#bloques-y-contenido-público) |
| Componer bloques y mostrar vistas del tema | `BlockRegistryInterface`, `BlockTree`, `<x-cms::block>`, `<x-cms::content-region>`, `FrontViewResolver` y `PageViewRegistry`. [Vistas y assets](EXTENSIONS.md#vistas-temas-y-assets) |
| Añadir una capacidad MCP o una integración | El registro de capacidades y los servicios de dominio que usa el panel. Para recursos externos, usa `ExternalContentPolicy`, `CookieConsentService` y las divulgaciones compartidas. [Privacidad](EXTENSIONS.md#contribuciones-de-privacidad) |

## Código limpio

- Usa PHP estricto, nombres de dominio y métodos con una responsabilidad clara. La configuración `pint.json` del paquete define el formato. Usa PHPStan nivel 8 donde el paquete aporta esa comprobación.
- Deja a controladores y herramientas MCP la validación de entrada y la respuesta. Las decisiones, transacciones y efectos posteriores al commit corresponden al servicio de dominio. Prepara datos y consultas antes de Blade.
- Extrae lógica compartida cuando tiene dueño y consumidores claros. Introduce una interfaz para un contrato real y un DTO para datos con significado. Mantén directas las llamadas y las dependencias.
- En el panel, adapta el componente existente con su API y tokens. Si el patrón es común y falta un componente, añádelo en Core con una prueba de la interacción. [Kit administrativo](EXTENSIONS.md#ui-del-backoffice-dbcms-y-termocms)

## Verificación y documentación

Comprueba el recorrido principal y los contextos que afecta el cambio. Incluye permisos, idioma, mercado o disponibilidad cuando formen parte de la entrada. Para el panel, comprueba teclado, móvil y ambas marcas si cambias el kit. Usa archivos y bases desechables. [Pruebas de Core](TESTING.md)

Escribe las guías en castellano y en presente. Documenta uso, decisiones y contratos vigentes en el documento propietario. Usa enlaces desde índices, skills y agentes para mantener una sola fuente por regla.
