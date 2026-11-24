# Personalizar secciones con clases CSS del tema

Usa `appearance.html_class` para enganchar el CSS del tema activo a secciones,
columnas y bloques. No confundas esta clave con `appearance.classes`: esa última
aplica **presets tipados** (`stack`, `row`, `card`) que Core traduce a
propiedades CSS; **no** escribe nombres de clase en el HTML.

## Contrato

| Clave | Qué hace | Quién la define |
| --- | --- | --- |
| `appearance.html_class` | Lista de tokens HTML (`folio-hero`, `intent`…) que Core añade al atributo `class` del nodo | Contenido (panel o MCP) + CSS del tema |
| `appearance.classes` | Presets de diseño → propiedades tipadas en un `<style>` scoped | Config `cms.blocks.classes` |
| `appearance.base` / dispositivos / estados | Declaraciones CSS tipadas | Inspector Diseño |

Reglas de `html_class`:

- Lista de strings; cada token cumple `^[a-z][a-z0-9_-]{0,63}$`.
- Máximo `cms.blocks.html_class_max` (16 por defecto).
- Si `cms.blocks.html_classes` está vacío, cualquier token válido vale.
- Si el tema publica una allowlist, solo esos nombres pasan la validación.
- Ausente o `[]` = comportamiento anterior (retrocompatible).

## 1. Tema: CSS y allowlist opcional

En el tema público, escribe selectores normales:

```css
.folio-hero {
    position: relative;
    min-height: 70svh;
}
.intent {
    padding-block: clamp(3rem, 8vw, 7rem);
}
```

Opcional, en el service provider del tema:

```php
$config = $this->app['config'];
$config->set('cms.blocks.html_classes', array_replace(
    (array) $config->get('cms.blocks.html_classes', []),
    [
        'folio-hero' => 'Hero editorial',
        'intent' => 'Bloque intent de portada',
    ],
));
```

Sin allowlist, el editor acepta cualquier token válido; con allowlist, el
inspector y MCP muestran las etiquetas y rechazan el resto.

## 2. Backoffice

1. Abre una página en el constructor de secciones.
2. Selecciona la sección (o un bloque) y ve a **Diseño → Avanzado**.
3. En **Clases CSS del tema** escribe `folio-hero` (o varias separadas por espacio).
4. Guarda y abre la vista pública: el nodo lleva `class="… folio-hero …"`.

**Presets de diseño** del mismo panel sigue siendo `appearance.classes`
(`stack`, `card`…): atajos tipados, no clases HTML.

## 3. MCP

1. `cms_block_types` con `view: "detail"` y un `block_type`. En `appearance.html_class`
   verás el patrón, el máximo y la allowlist del tema.
2. Al crear o parchear secciones, incluye:

```json
{
  "type": "text",
  "columns_layout": "1",
  "appearance": {
    "html_class": ["folio-hero"],
    "extent": "viewport"
  },
  "columns": [
    {
      "blocks": [
        {
          "id": "title",
          "type": "heading",
          "text": "Cómo es el tratamiento",
          "appearance": { "html_class": ["folio-title"] }
        }
      ]
    }
  ]
}
```

3. Con `cms_content_sections_patch`, actualiza solo `appearance` del `node_id`
   de la sección o del bloque. Vuelve a leer con `cms_content_sections_get`.

## 4. Capturas de verificación

Con una aplicación desechable, tema compilado y sesión de Playwright
(`DBCMS_BROWSER_BASE_URL`, `DBCMS_BROWSER_STORAGE_STATE`, token MCP si aplica):

```bash
DBCMS_HTML_CLASS_CAPTURES=/tmp/dbcms-html-class-captures \
  npx playwright test --config tests/Browser/playwright.config.mjs html-class-theme.spec.mjs
```

Las capturas quedan en el directorio indicado: inspector Avanzado, vista previa
pública con la clase en el DOM, y resumen MCP del esquema + parche.

## Relación con plantillas

Las **Plantillas** de composición pueden llevar `html_class` ya relleno. Al
insertarlas en una página, el tema recibe el gancho sin depender de selectores
`:has([data-cms-node=…])`. Conserva ids de bloque para edición; usa clases HTML
para presentación de marca.
