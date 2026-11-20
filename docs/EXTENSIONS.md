# Extensiones y Blade

El [contrato de arquitectura](CORE_AND_EXTENSIONS.md) define propietarios
y disponibilidad. Composer descarga y versiona código; el CMS instala y activa
contribuciones explícitamente. No se ejecuta un gestor de paquetes desde HTTP.
Para crear una web utiliza el [instalador](https://github.com/dbravoan/dbcms-installer).

Antes de escribir una extensión, identifica un caso equivalente en Core o en un
paquete y reutiliza su contrato. [Convenciones de desarrollo](CONVENTIONS.md#buscar-y-reutilizar-antes-de-implementar)

## Contratos de paquetes

| Contribución | Tipo Composer | Ejemplo |
| --- | --- | --- |
| Módulo de negocio | `dbcms-module` | `dbravoan/dbcms-module-blog` |
| Bloques visuales | `dbcms-block-package` | `dbravoan/dbcms-blocks-scrollytelling` |
| Tema público | `dbcms-theme` | `dbravoan/dbcms-theme-default-clean` |
| Tema administrativo | `dbcms-admin-theme` | `dbravoan/dbcms-admin-theme-termocms` |

Todos los tipos mantienen código, recursos, pruebas y versiones en su propio
repositorio. Comprueban sus dependencias obligatorias y sus integraciones
declaradas. Las plantillas Blade y las composiciones siguen al paquete que las
aporta y se prueban con él. Los contratos compartidos tienen una única fuente;
cada propietario documenta únicamente su uso y sus requisitos específicos.

Declara dependencias en `require`, namespaces en `autoload.psr-4` y contribuciones
en `extra.dbcms`. Cada contribución necesita `identifier` único. Los módulos
declaran su `provider`; los paquetes visuales, sus clases de bloques.
`DbCms\Core\Extensions\ComposerPackages` lee el inventario instalado de Composer,
sin explorar directorios arbitrarios ni escribir estado. `module.json` contiene
ajustes y contribuciones, no otro resolvedor de versiones: no puede sustituir
las dependencias de Composer. Los proveedores pueden utilizar namespaces de terceros
y deben residir en su paquete. Las versiones publicadas/desarrollo siguen Composer.

Los permisos de un módulo se declaran en `extra.dbcms.permissions` o en
`module.json` (`permissions`) y se incorporan al catálogo mediante
`AccessProvisioner` en la instalación o `cms:access:sync` en una instalación
existente. Cada permiso usa la forma `acción:recurso`, como `manage:posts`. Las rutas y Gates del
módulo lo referencian sin crear filas manualmente.

Cada paquete tiene su propio versionado y expresa compatibilidad mediante `require`.
Una versión del módulo puede requerir core `^0.1`; sus números son independientes. Véase
[Publicación](PUBLICATION.md) para tags y distribución.

Ejemplo mínimo:

```json
{
  "name": "acme/dbcms-events",
  "type": "dbcms-module",
  "require": {
    "dbravoan/dbcms-core": "~0.0.1"
  },
  "autoload": {
    "psr-4": {
      "Acme\\Events\\": "./"
    }
  },
  "extra": {
    "dbcms": {
      "identifier": "events",
      "name": "Events",
      "provider": "Acme\\Events\\EventsServiceProvider"
    }
  }
}
```

Un proveedor que extiende `DbCms\Core\Modules\BaseModuleServiceProvider` resuelve
sus rutas administrativas en `Routes/admin.php` respecto al directorio del proveedor.
Puede residir en la raíz o en `src/`. Mantén las rutas y migraciones junto a ese
propietario. La base aporta autenticación y
disponibilidad; cada ruta sigue necesitando sus permisos de recurso. Registra
proveedores mediante metadatos dbCMS, no descubrimiento Laravel que omita el ciclo
de vida del módulo.

Usa paquetes existentes como ejemplos de propietario: migraciones, entidades,
ajustes, lectores públicos, navegación y capacidades pertenecen a su módulo.
Una entidad con revisión editorial registra su esquema/handler en las mutaciones
compartidas. URLs, búsqueda y descubrimiento semántico son explícitos; un esquema
administrativo no vuelve públicos modelos privados. Las mutaciones relacionadas
son transaccionales y los efectos remotos se emiten después del commit.

`CMS_MODULES` selecciona la composición inicial del aprovisionamiento.
Una vez sellada la instalación, el registro de módulos instalados determina
qué proveedores carga Core.

Tras `composer require` mediante Sail, recompila assets e instala/activa desde
**Módulos**. Desactiva/desinstala antes de quitar una dependencia Composer. Los
datos se conservan salvo una operación separada autorizada. La disponibilidad usa
`ModuleManager::isModuleEnabled()`; las instancias desactivadas se preservan.
Paquetes gratuitos, privados y de pago comparten contrato y autenticación Composer;
la política de descarga pertenece al servicio de distribución.

## Bloques y contenido público

Un bloque extiende `DbCms\Core\Blocks\AbstractBlockType` y define tipo estable,
esquema/validación de editor, valores iniciales, rutas traducibles y render.
Un módulo registra mediante `BlockRegistryInterface::register()` y declara
`getRequiredModule()`. Un paquete visual enumera clases en `extra.dbcms.blocks`.
Todos comparten registro y decisión de disponibilidad. Implementa
`ComposableBlockInterface` cuando la raíz del bloque recibe la apariencia
directamente. La sección aporta el layout y los estilos explícitos de su fila.

Mantén consultas en el lector público propietario. Aplica publicación, idioma,
mercado y disponibilidad antes de seleccionar datos. `PublicSemanticRegistry`
acepta contribuidores semánticos/URL explícitos. Un bloque implementa
`PublicWidgetSemanticContributorInterface` solo si aporta texto público. Búsqueda
y SEO consumen estos lectores y no ejecutan Blade arbitrario para extraer texto.

## Composición y payload de bloques

El **esquema de cada bloque** (`getEditorSchema()`) y sus valores iniciales
(`getDefaultPayload()`) definen los datos aceptados. No hay un catálogo manual
paralelo. Consulta primero el resumen y después el tipo concreto, desde Sail:

```bash
./vendor/bin/sail artisan cms:blocks:describe --market=es --locale=es
./vendor/bin/sail artisan cms:blocks:describe accordion --market=es --locale=es --format=markdown
```

El editor ofrece **Ver datos** y **Copiar ejemplo Blade** sobre el nodo seleccionado.
El comando admite `--entity-type` y `--entity-id` para comprobar el contexto. Los
valores iniciales son ejemplos, nunca hechos sobre una empresa o sus productos.

Un campo editorial `type: blocks` declara una zona de composición; puede estar
dentro de un `repeater` u `object`. `drawer` usa `children`. Acordeones,
pestañas y carruseles usan `items.*.children`. Solo se recorren zonas declaradas
por el propietario. Las secciones y traducciones conservan su contrato exterior
`columns[].blocks[]`; no existe un documento separado para el editor.

Un bloque con `BlockAuthoringInterface` declara presets en `cms_block_types`
y permite consultar sus colecciones con `view: "authoring"`, `node_id` y
`locale` en `cms_content_sections_get`. Los repetidores editables usan
`keyed: true` y declaran el campo `id`. `target` selecciona una fila con una
lista de pares `{collection, id}`. Para leer un preset, consulta el tipo con
`view: "detail"` y `preset`.

Con la `version` de la lectura, usa `insert_item`, `update_item`, `move_item`
o `remove_item` en `cms_content_sections_patch`. Para insertar una fila,
indica su colección y su `item`. `before_id` permite elegir un hermano como
referencia. Usa `slot_target` para insertar o mover un bloque en la zona
de composición de una fila. Core valida el lote y aplica el flujo editorial.
Implementa `BlockPayloadValidationInterface` para comprobar relaciones entre
campos después de validar el esquema.

Prepara `$accordion` en el código que aporta los datos a la vista.

```blade
@php
$accordion = [
    'items' => [[
        'title' => '¿Cómo puedo contactar?',
        'children' => [
            ['type' => 'rich_text', 'format' => 'markdown', 'content' => 'Consulta nuestra página de contacto.'],
            ['type' => 'button', 'label' => 'Contacto', 'url' => '/contacto'],
        ],
    ]],
];
@endphp
<x-cms::block type="accordion" :payload="$accordion" />
```

El componente y las secciones comparten validación, disponibilidad, referencias,
apariencia, hijos, lectores y assets. En Blade se necesita contexto público
(`cms.front` o un mercado e idioma explícitos); repetir el payload genera IDs DOM
distintos. Los `id` editoriales identifican nodos y no deben repetirse dentro de
un idioma. Duplicar renueva descendientes; traducir conserva su relación.

### Inspector de apariencia

Los accesos de apariencia de secciones, columnas y bloques abren un mismo
inspector. **Contenido** reúne los campos del propietario. **Diseño** muestra
primero esenciales según el rol: sección (fondo, ancho, relleno, gap), columna
(fondo simple y relleno) o bloque (tipografía básica, espaciado y radio).
Dispositivo, estado, tipografía extendida, bordes completos, separadores y
movimiento viven bajo **Más opciones**. **Avanzado** concentra posición, capas,
CSS libre, clases y bindings. Los controles aplicables, unidades y grupos
proceden del catálogo compartido. La alineación usa botones y los degradados
básicos se editan con dos colores, tipo y ángulo; uno personalizado permanece
intacto hasta sustituirlo o editarlo en Avanzado.

La vista privada muestra la composición con el tema real y permite seleccionar
sus piezas. Conserva la última vista válida mientras editas un valor incompleto.
En móvil puedes alternar entre el inspector y la vista sin perder la selección.

`appearance` admite `base`, `tablet`, `mobile`, `hover`, `focus`, `active`,
`selected`, `classes` y `extent`. Escritorio, tableta y móvil heredan del tema y
de los tamaños superiores; sus controles permiten restablecer la herencia. Los
estados son comunes a los dispositivos: `focus` sigue el foco de los controles y
`selected` se aplica a controles seleccionables cuando existen. No son combinaciones
independientes de estado y dispositivo. Las clases permitidas y breakpoints se
configuran en `cms.blocks`.

Se distinguen **anchura del bloque** (dentro de su padre), **anchura interior**
(límite de su contenido) y **extensión de la fila**. `appearance.extent` vale
`theme` por defecto o `viewport` para una raíz de región extensible. `100%` llena
el padre; no significa ancho de ventana. Los nodos dentro de columnas, pestañas,
carruseles o paneles permanecen contenidos. Los temas usan la región compartida;
una página Blade puede declararla también:

```blade
<x-cms::content-region>
    <x-cms::block type="accordion" :payload="[
        'appearance' => ['extent' => 'viewport'],
        'content_width' => 'centered',
        'bg_type' => 'image',
        'bg_image_url' => '/storage/cabecera.webp',
        'bg_image_overlay' => 'rgba(0,0,0,0.35)',
        'bg_behavior' => 'parallax',
        'items' => [[
            'title' => 'Más información',
            'children' => [['type' => 'rich_text', 'format' => 'markdown', 'content' => 'Contenido centrado.']],
        ]],
    ]" />
</x-cms::content-region>
```

La región debe disponer del espacio que ofrece el layout: no saca un bloque de
un contenedor arbitrario del proyecto. Los temas conservan por defecto sus
anchuras y márgenes. Fondos, superposiciones y movimiento comparten resolución y
render con secciones, columnas y Blade. **Animaciones** del inspector son solo
entrada al viewport (`animation` / `scroll_magic.behavior`). Configura el
paralaje continuo en Diseño con `bg_behavior` (`parallax` / `fixed`) sobre una
imagen de fondo. El motor actúa sobre las capas propias,
libera sus recursos al retirarlas y respeta movimiento reducido y consentimiento.

Los valores de `appearance` tienen prioridad sobre los campos de presentación
del nodo y los del tema. El inspector cambia únicamente el concepto que editas.
Los campos editoriales admiten propiedades del catálogo y clases permitidas.

El editor permite recorrer la estructura, editar zonas declaradas, arrastrar o
mover con teclado, duplicar y deshacer o rehacer durante la sesión.

Los límites configurables `cms.blocks.max_depth`, `max_nodes` y
`max_payload_bytes` acotan la entrada; `max_render_nodes` y `max_render_bytes`
acotan el render. Los propietarios no disponibles son opacos: se conservan en
edición sin consultar ni ejecutar su contribución pública.

### Secciones, bloques y plantillas

La **sección** es el layout de página: columnas, estilos y animaciones. Dentro de
cada columna viven **bloques** genéricos del registro. «Añadir sección» crea una
sección vacía; «Añadir bloque» abre el catálogo (en blanco o con datos de ejemplo
por tipo) con zonas de inserción arriba, entre y abajo de cada columna.

El tipo editorial nativo es `rich_text`. Usa su modo Visual con Tiptap o
conserva la fuente HTML o Markdown. Un bloque específico aporta opciones propias,
como la cita con autor y fuente o el vídeo con subtítulos. Usa `type: "text"`
como marcador de sección con columnas y `type: "rich_text"` en sus bloques de texto.

Las **Plantillas** son entidades de admin bajo Páginas (`block_components*`,
permiso `manage:pages`). `CompositionTemplateSeeder` prepara las composiciones
iniciales durante la instalación. Cada plantilla contiene secciones con el mismo
esquema que una página. Abre **Añadir bloque → Plantillas** para previsualizar
e insertar una copia independiente de sus secciones y nodos. Para listas de
contenido vivo, usa bloques que consultan al propietario, como `posts` de Blog
o `form` de Core.

## Catálogo para asistentes y medios

`cms_block_types` necesita `entity_type`, `locale` y `market`; admite `entity_id`.
Empieza con `view: summary`. Solicita `block_type` para cargar solo su esquema y
valores iniciales. La apariencia común se devuelve una vez. Las referencias se
consultan con `cms_block_references`, `source`, búsqueda, `limit` (máximo 50) y
`offset`; sus propietarios implementan `SearchableBlockReferencesInterface`. No
se precargan todas las referencias del editor ni se transmiten al modelo árboles
completos para una modificación pequeña.

Para editar, `cms_content_sections_get` con `view: "summary"` o `node_id` devuelve nodos y `version`.
`cms_content_sections_patch` recibe `type`, `id`, `locale`, esa `version` y
hasta 100 operaciones. Usa `update`, `insert`, `remove` o `move` en nodos de
bloques, y las operaciones de [colecciones con ID](#composición-y-payload-de-bloques)
para sus filas. `node_id` identifica el nodo y `values` actualiza sus campos.
Indica `parent_id`, `slot` e `index` para colocar un bloque. Para una columna,
usa el slot `columns.<índice>.blocks` que devuelve el resumen. Omite el padre
para operar en la lista de secciones. El servidor exige la versión actual. Las secciones comparten orden
entre idiomas y se agrupan por identidad, nunca reasignando traducciones por posición.
El patch aplica el orden del idioma editado; la sincronización completa puede indicar
`order_locale`. Sin esa opción se utiliza el idioma activo predeterminado y un
fallback determinista, no el orden de las claves JSON. Los formularios transportan
esta elección mediante `sections_order_locale`. Sigue disponible la lectura
completa y `cms_content_sections_sync`; ambas mutaciones usan el mismo writer,
permisos y flujo editorial opcional. No se necesita un proveedor de IA.

La biblioteca admite subida de archivos y, con `cms_media_import`, importación de
imágenes desde URL pública. `cms_media_upload` recibe `filename` y
`content_base64`; importar recibe `url`, `title` y `alt_text` opcionales. Ambas
operaciones requieren `manage:media` y funcionan sin AiAssistant. Las imágenes
se guardan en el almacenamiento configurado, con contenido validado y límite de
50 MB. Las URLs remotas no pueden contener credenciales, resolver a redes privadas
ni depender de redirecciones. La URL final pertenece a la biblioteca, no al
proveedor generativo. Subir o generar una imagen no publica contenido. La biblioteca también admite
audio, subtítulos WebVTT y documentos Lottie JSON validados: sin expresiones ejecutables, fuentes ni
recursos externos. Swiper modular y Lottie Web se cargan por demanda; los recursos
exclusivos de edición (arrastre y texto visual) no se cargan al visitar la web.
Los módulos pueden aportar `Resources/js/blocks.js` o `public.js`, y los paquetes de
bloques `Resources/css/blocks.css`, mediante el inventario Composer de Vite.
Cada instancia libera observadores, temporizadores y reproductores al desaparecer.

Un paquete que defina `getEditorView()` puede aportar `Resources/js/admin.js`.
En la vista, monta el editor con `mountPackageBlockEditor($el, blockKey)` en el
gestor. El módulo exporta por defecto una función que recibe el host y el
acceso al editor de Core, y devuelve su limpieza.
`extra.dbcms.block_asset_marker` activa la carga del runtime público por el
marcador propio `data-cms-<identificador>`.

Los servicios externos usan `ExternalContentPolicy`, consentimiento y CSP comunes.
Una cuenta atrás por visitante usa memoria por defecto. Para habilitar persistencia,
configura `cms.blocks.countdown_storage`, documenta/revisa el tratamiento
`block_countdowns` y obtiene consentimiento para el propósito configurado; sin esa
autorización no se accede al almacenamiento ni se promete continuidad entre visitas.
Las acciones de formulario se configuran en Forms. [Usar dbCMS Core](USAGE.md#configurar-el-sitio)

Los archivos que se copien directamente a `public/uploads` se incorporan a la
biblioteca mediante `php artisan cms:media:reconcile`. El comando conserva los
metadatos de los archivos ya registrados.

## Vistas, temas y assets

Un tema público contiene `theme.json`, `layouts/app.blade.php`, overrides y
`theme.css`. Un tema admin contiene su manifest, vistas de marca y assets. La
versión procede de Composer, no de un campo paralelo en `theme.json`.
El entorno selecciona identificadores instalados mediante `CMS_THEME` y `CMS_BRAND`.
Cada tema aporta su layout, sin fallback implícito a otro tema.

La resolución es override del proyecto, tema seleccionado y vista del propietario.
Los overrides públicos residen en `resources/views/themes/<theme-id>/`, expuestos
mediante `theme-<theme-id>::`. Los overrides Laravel de módulos residen en
`resources/views/vendor/<namespace>/`. Usa
`DbCms\Core\Services\Cms\FrontViewResolver`, sin duplicar la resolución.
Usa `themes::components.nav-items` y `themes::components.nav-panel` para
renderizar el árbol `children` del menú público en escritorio y en el panel móvil.
Los controles admin compartidos usan `<x-admin.*>` semánticos; conserva etiquetas,
teclado y las marcas instaladas.

### UI del backoffice (dbcms y termocms)

La estructura, componentes y clases semánticas del panel viven en el núcleo
(`resources/views/components/admin/*`,
`resources/css/admin-ui.css`). Los temas admin
`dbcms` y `termocms` solo aportan tokens (`--admin-*`) y, si hace falta, layout
de marca; no reinventan inputs, botones ni tipografía de formulario.
Módulos y paquetes nuevos reutilizan este kit; si falta un primitivo compartido,
se amplía en el núcleo. [Convenciones](CONVENTIONS.md#buscar-y-reutilizar-antes-de-implementar)

Al implementar o revisar pantallas del panel:

- Usa `<x-admin.button>`, `<x-admin.input>`, `<x-admin.select>`,
  `<x-admin.form-card>`, `<x-admin.table>`, `<x-admin.datatable-header>`,
  `<x-admin.form-header>`, `<x-admin.modal>`, etc., o las clases `admin-control`,
  `admin-field`, `admin-field-label`, `admin-field-hint`, `admin-check-label`,
  `admin-btn--*`.
- Diálogos: **siempre** `<x-admin.modal>` (título a la izquierda; cierre
  `admin-btn--icon` + `fa-xmark` a la derecha; slots `toolbar` / `footer` si
  hace falta). Sin botón de texto «Cerrar» en la cabecera ni cáscaras `fixed`
  paralelas.
- Listados y formularios: misma filosofía que el modal (`table`,
  `datatable-header`, `form-card`, `form-header`).
- Toolbars densas (gestor de bloques): icono + `title` / `aria-label`; las
  acciones primarias de página pueden llevar etiqueta.
- No pintes controles con utilidades Tailwind de borde/fondo/padding/fuente que
  peleen con `[data-admin-shell] .admin-control`. Los temas reescriben utilidades
  con selectores frágiles; las clases semánticas son la vía estable.
- No dupliques un kit por tema ni añadas CSS de formulario solo en un tema.
- El gestor de bloques y el inspector siguen el mismo kit; no crees un segundo
  estilo «inspector» con inputs más pequeños o `font-mono` de marca.

La biblioteca multimedia se abre en `admin.media.index` (`GET /<admin>/media`, HTML).
Biblioteca y selector comparten `admin.media.files` (`GET /<admin>/media/files`, JSON
con `files` y `pagination`) y los filtros `search`, `type`, `page` y `per_page`.
El selector conserva los eventos `open-filemanager`, `file-selected` y
`filemanager-closed`, con destino y tipo de medio. Todas las operaciones requieren
`manage:media`; las mutaciones conservan CSRF y la validación del núcleo.

Los botones de fila comparten `action-button`, con nombre accesible y `type`
explícito para envíos. El modal compartido controla el foco y Escape, incluido el
retorno al diálogo anterior cuando hay confirmaciones superpuestas. Las
cabeceras con búsqueda solo se usan cuando la consulta procesa sus controles;
los detalles y listados sin filtros usan `form-header`.

Vite compila paquetes instalados y assets propios. Ejecuta
`./vendor/bin/sail npm run build` tras cambios de paquetes. Mantén fuentes e imágenes
predeterminadas locales y no escribas compilados en vendor. Las personalizaciones
pertenecen a assets/vistas del proyecto o a un tema requerido por separado.

## Variables opcionales hacia el tema admin

Los temas admin (`admin.layout` y vistas `admin.*`) pueden recibir variables de
vista opcionales que un módulo o el núcleo aportan con `View::composer` o en el
DTO de la vista. No forman parte del contrato de [núcleo y extensiones](CORE_AND_EXTENSIONS.md);
son convenciones de presentación. Pintarlas solo cuando existan / tengan valor.

| Variable | Tipo | Quién la aporta | Cuándo se pinta |
| --- | --- | --- | --- |
| `inactive` | `bool` | Núcleo (`ModuleSettingsAdministrationService`) en ajustes de módulo | Aviso en el layout admin si el módulo está instalado pero no activo |
| `modulePrivacyWarnings` | `array<string, array{message: string, label: string}>` | Compositor de la vista que aporta los avisos del módulo | Enlace a `admin.settings.privacy` junto al módulo cuando falta su tratamiento |
| `config('cms.adminer_url')` | `string` (config, no variable de vista) | Proyecto / entorno | Enlace Adminer en el layout admin fuera de producción, mismo patrón `@if(... && config('cms.adminer_url'))` |

Ejemplo (aviso de módulo inactivo en el layout):

```blade
@if(! empty($inactive))
    <p role="status">…</p>
@endif
```

Ejemplo (aviso de privacidad en el listado de módulos):

```blade
@if(isset($modulePrivacyWarnings[$module->identifier]))
    <a href="{{ route('admin.settings.privacy', ['owner' => $module->identifier]) }}">
        {{ $modulePrivacyWarnings[$module->identifier]['label'] }}
    </a>
@endif
```

La configuración de privacidad del panel (`admin.settings.privacy`) también es
alcanzable desde Ajustes del sitio. No rellenes variables de layout huérfanas
si la UI ya enlaza esa ruta.

## Páginas Blade

Registra vistas controladas por código en `config/cms.php` del proyecto:

```php
return [
    'page_views' => ['landing' => 'pages.landing'],
];
```

Crea `resources/views/pages/landing.blade.php` y selecciona `blade:landing` en
la página del panel. `PageViewRegistry` solo permite claves registradas, reserva
las vistas legales y falla explícitamente si falta una vista. Page sigue siendo
propietaria de traducciones, mercados, permisos, publicación y snapshots.
Las secciones existentes permanecen almacenadas, sin edición ni render en modo Blade.

Si el editor necesita cambiar parte del contenido, declara los campos junto a la vista en `cms.page_views`:

```php
'landing' => [
    'view' => 'pages.landing',
    'fields' => [
        'headline' => ['label' => 'Encabezado', 'default' => 'Bienvenidos'],
        'intro' => [
            'label' => 'Introducción',
            'type' => 'rich_text',
            'default' => ['format' => 'markdown', 'content' => '**Conócenos**'],
        ],
    ],
],
```

```blade
<h2>{{ $templateData['headline'] }}</h2>
<div class="cms-rich-text">{{ $templateData['intro'] }}</div>
```

El tipo predeterminado `text` usa un campo de una línea de hasta 255 caracteres.
Usa `rich_text` para contenido de hasta 16 000 caracteres que requiera formato.
Se edita con el mismo componente que los bloques **Rich Text** del constructor.
Visual edita la fuente `html`. Los modos Visual, Markdown y HTML comparten
previsualización y biblioteca multimedia. Convertir Markdown con contenido a
HTML requiere confirmación. La conversión a Markdown se acepta cuando puede
conservar el resultado renderizado. Si Visual no puede representar la estructura,
el editor abre la fuente HTML. Cada idioma conserva su valor y formato.

En `$templateData`, imprime con `{{ }}`. Core entrega los campos `text` como
cadenas y los `rich_text` como HTML saneado para el cuerpo de la página.
Usa `$translation` para el título y los metadatos. Las cadenas fijas de la vista
se pueden traducir mediante los archivos de idioma del proyecto.

En MCP, consulta `cms_entity_types` y busca la entrada `page`. Su mapa
`blade_templates` incluye las vistas del proyecto disponibles y los campos que
declaran. Para crear un borrador bilingüe, llama a `cms_entity_create` con estos argumentos.

```json
{
  "type": "page",
  "attributes": {"template": "blade:landing"},
  "translations": {
    "es": {
      "title": "Inicio",
      "template_data": {
        "headline": "Bienvenidos",
        "intro": {"format": "markdown", "content": "**Conócenos**"}
      }
    },
    "en": {
      "title": "Home",
      "template_data": {
        "headline": "Welcome",
        "intro": {"format": "html", "content": "<p>About us</p>"}
      }
    }
  }
}
```

Confirma el resultado con `cms_entity_get`. Para revisar un campo, envía el
cambio en `translations.<idioma>.template_data` con `cms_entity_update`.
Se conservan los campos e idiomas omitidos en la misma plantilla. Usa un idioma
configurado e incluye `title` al guardar campos en un idioma nuevo. Envía `null`
o vacía el campo para recuperar el valor inicial de la plantilla. Al seleccionar
otra plantilla, sus campos parten de sus propios valores iniciales. Las ediciones siguen el
flujo editorial habitual.

Los campos públicos usan la traducción del mercado solicitado y, si está vacía,
la fila global del mismo idioma. Nunca otro mercado o idioma. Un slug regional
prevalece en ese mercado; enlaces, búsqueda, sitemap, texto semántico y secciones
usan el mismo contexto. Las traducciones y snapshots conservan sus filas originales.
Cada campo Blade sin traducción hereda el valor compartido de la página o el
inicial de la plantilla. En el panel y MCP se editan las filas globales de cada
idioma. `cms_entity_get` devuelve esa fila cuando existe. Una variante regional
existente tiene prioridad campo a campo al renderizar.

Dentro de una vista registrada puedes reutilizar bloques de módulos instalados:

```blade
@extends(app(\DbCms\Core\Services\Cms\FrontViewResolver::class)->layout())

@section('content')
    <x-cms::block type="posts"
        :payload="['selection' => 'latest', 'limit' => 3]" />
    <x-cms::block type="form"
        :payload="['form_slug' => 'contact']" />
@endsection
```

El ejemplo necesita Blog activo; formularios y el formulario público `contact` pertenecen al núcleo.
El componente valida payload y utiliza los mismos lectores, disponibilidad e
idioma/mercado que el render de secciones. No evita sus controles.

Para páginas por ruta, usa rutas Laravel normales con middleware `cms.front`.
Aporta contexto público, no otro router ni publicación automática. El controlador
personalizado responde de la autorización. Blade no tiene caché HTML de página
completa por defecto, conservando CSRF/sesión por petición. Un documento completo
incluye `@include('themes::components.cookie-consent-modal')`.

## Contribuciones de privacidad

Los embeds ejecutables declarados usan entradas `privacy.external_services` con
`provider`, `purpose` y `origins` HTTPS exactos. Su tratamiento revisado pertenece
a `external_<service-id>` y describe datos, base jurídica, destinatarios,
transferencias y conservación. Selecciona el servicio en Video/Code Embed.
Las URLs no declaradas permanecen no disponibles y los embeds declarados inertes
hasta obtener consentimiento de contenido externo. Code Embed limita también su
sandbox a los orígenes declarados. Cambiar proveedor/origen cambia la revisión.

CSP limita imágenes, medios y fondos CSS al origen del sitio. Los servicios de
hosting/CDN necesarios se declaran en `privacy.asset_services` con la misma forma
y tratamiento revisado `assets_<service-id>`. No utilices esa vía para seguimiento.
Los orígenes opcionales solo se permiten con recibo vigente. Cambiar una opción
recarga el documento para aplicar CSP y detener scripts de terceros anteriores.

Implementa `DbCms\Core\Security\Contracts\CookieDisclosureContributorInterface`
para describir almacenamiento e integraciones efectivos. Registra con la etiqueta
`cms.cookie-disclosures`; `revisionData()` contiene destinos públicos efectivos,
sin credenciales. No aportes información/revisión opcional si el módulo o
integración no está disponible. La necesidad/opcionalidad pertenece al servicio
compartido, no a parsers de cookies específicos por módulo.

`CookieConsentService::hasConsent($purpose, $request)` decide en servidor para
la petición actual. Scripts, contenido externo y seguimiento opcionales esperan
consentimiento; los trabajos vuelven a comprobar autorización. Usa UI y endpoints
compartidos, no otro banner. Los workers transportan el ID opaco del recibo y
llaman `hasConsentForReceipt` antes de enviar. Caducidad, retirada o revisión de
política lo invalidan. `consentReceiptId` opcional de Meta CAPI utiliza ese lector.

`LegalPageService` posee información de responsable/empresa y tratamientos.
El instalador crea páginas legales localizadas, contacto editable y enlaces de navegación. Exige tratamientos core, forms y executions revisados y con conservación finita. La pantalla de
privacidad del panel permite revisar los datos y tratamientos existentes; el
responsable confirma que corresponden a la instalación. Para automatización,
ejecuta `privacy:configure <file>` mediante Sail conforme a `LegalCompanyDTO`, con
la lista completa de tratamientos; el archivo privado no entra en Git. Ambas vías
sincronizan la conservación y la rotación de logs; el formulario general solo
modifica contacto/empresa.
Un idioma nuevo necesita traducciones legales completas antes de activarlo.
El núcleo incluye plantillas legales para `es`, `en`, `ca`, `fr`, `it`, `pt`,
`de`, `ar` y `zh-CN`; añadir otro idioma exige crear `lang/<locale>/legal.php` y
`lang/<locale>/privacy.php` completos antes de activarlo.

El tratamiento obligatorio `core` declara `retention_days` positivo para metadatos
de seguridad, notificaciones e historiales terminados. Instalación y configuración
de privacidad fijan rotación diaria. Configura el scheduler: `privacy:prune`
retira registros vencidos, logs locales fechados y tokens, sesiones, restablecimientos
y trabajos vencidos/finalizados. Correo en curso y trabajo pendiente requieren
revisión. Logs externos y backups necesitan su propio ciclo coherente.

`accounts:privacy export <id> --verified` exporta a almacenamiento local privado;
`erase` revoca credenciales y retira copias de contacto/sesión del núcleo. Borra
exports tras atender la solicitud. Verifica identidad, alcance y conservación antes
de usar `--verified`: el flag no realiza esa revisión. Se protegen el último admin
humano, trabajo en curso y referencias de autor de módulos. Los propietarios
correspondientes gestionan sus datos. El registro API público requiere tratamiento
`accounts` revisado. La conservación de cuentas usa criterio documentado con
`manual_review: true`, sin `retention_days`, porque no se borran cuentas activas
automáticamente. Los formularios propios muestran
`<x-cms::privacy-notice owner="accounts" :allow-manual-review="true" />` antes de recoger datos.

Los módulos con datos personales poseen exportación, borrado y conservación,
incluso desactivados. Consulta los [comandos de Core](USAGE.md#operar-mediante-artisan)
y los del paquete propietario.
Configura fines reales, bases jurídicas, destinatarios, transferencias y retención.
Las páginas generadas no certifican cumplimiento; adapta otras jurisdicciones.

Verifica instalación real, propietarios ausentes/desactivados, ambas marcas admin,
overrides, publicación/localización y privacidad según [Pruebas](TESTING.md).

### Previsualización editorial

Los esquemas de entidades auditables pueden declarar `previewHandler`, una clase
que implementa `EditorialPreviewHandlerInterface`. Recibe el modelo hidratado
con el snapshot y el contexto de mercado e idioma y devuelve `FrontPageData`.
El propietario prepara su vista a partir de las relaciones hidratadas, sin
consultar de nuevo la entidad publicada. La previsualización es privada y de
solo lectura; el tema y los contenidos relacionados reflejan el contexto actual.

El iframe conserva origen opaco y JavaScript de presentación; omite barra de
admin, preferencias de sesión y seguimiento. Los recursos públicos compilados
en `/build/assets` necesitan `Access-Control-Allow-Origin: *`; esta regla no se
aplica a HTML ni API. La previsualización utiliza el build de producción: en
desarrollo detén Vite HMR por su cierre normal (que retira `public/hot`) y
ejecuta `npm run build` en el entorno del proyecto. No se abre CORS del servidor HMR.
`cms:setup` genera `server.php`, que usan `php artisan serve` y Sail.
Para el servidor de producción consulta el
[despliegue de la aplicación](https://github.com/dbravoan/dbcms-installer/blob/main/docs/DEPLOYMENT.md#preparar-el-vps).
