# Núcleo y extensiones

Este es el contrato canónico de arquitectura. Las aplicaciones Laravel consumen
dbCMS mediante Composer. El código del proyecto pertenece a `App`; el CMS
reutilizable a `DbCms\Core`. La aplicación incorpora módulos, temas y paquetes de
bloques mediante Composer. El código se cambia en el repositorio propietario y
la aplicación lo consume como dependencia.

## Responsabilidades y ciclo de vida

El núcleo posee instalación, identidad/permisos, localización, medios, páginas,
menús, publicación, secciones, resolución de temas, SEO técnico, privacidad, formularios, antispam, búsqueda pública, mantenimiento y transporte MCP.
Los módulos poseen sus entidades, migraciones, rutas, navegación, capacidades y
operaciones sobre datos personales. Los componentes y la apariencia admin
predeterminada pertenecen al núcleo; las marcas adicionales a los temas admin.
Cada módulo, tema y paquete de bloques externo posee su código, pruebas y
distribución. El catálogo del instalador incluye los módulos oficiales publicados.

Composer gestiona versiones, dependencias y autoload. Los metadatos CMS describen
contribuciones. Descubrir paquetes instalados no escribe estado. La instalación
explícita ejecuta las migraciones seleccionadas y registra estado. Disponibilidad,
instalación y activación son distintas. Desactivar conserva datos y rechaza
dependientes activos; desinstalar rechaza dependientes instalados. Las contribuciones
públicas desactivadas no ejecutan, consultan, aparecen en selectores ni aportan semántica.

Cada paquete tiene versionado independiente y expresa su línea compatible mediante Composer. Las restricciones Composer expresan compatibilidad, no igualdad
de versiones. Los tags publicados son la fuente de versiones; los repositorios
`path` se utilizan exclusivamente para el candidato bajo prueba en una aplicación
desechable. Véase [Publicación](PUBLICATION.md).

Los bloques comparten registro y decisión de disponibilidad. Los bloques nativos
forman parte de Core. El panel permite habilitar paquetes externos y el
aprovisionamiento activa los paquetes que selecciona la aplicación.
Las instancias desactivadas se conservan como elementos no disponibles y se
recuperan al activar. Los cambios de disponibilidad reindexan sincrónicamente en
su transacción y reinician workers después del commit. Descubrir no hace ninguna
de las dos cosas. No se añaden registros duplicados ni autoloaders propios.

## Presentación y páginas

La resolución prioriza override del proyecto, tema seleccionado y vista del
propietario. Cada tema posee su layout; no hay fallback implícito entre temas.
Vite compila assets de paquetes instalados y overrides sin escribir en vendor.
Las fuentes e imágenes predeterminadas son locales.

La plantilla Page `blade:<key>` resuelve una vista registrada en código, nunca una
ruta arbitraria proporcionada por un editor. Pages conserva publicación,
traducciones, aislamiento de idiomas/mercados, permisos, SEO y snapshots. La vista
puede declarar [campos editables por idioma](EXTENSIONS.md#páginas-blade) para el
panel y MCP. El modo Blade conserva secciones latentes, rechaza sus ediciones y
las excluye de la semántica pública. Los snapshots siguen capturando/restaurando
secciones canónicas.
Una vista registrada ausente falla explícitamente, sin volver silenciosamente a
secciones. Las páginas Blade por ruta usan rutas Laravel con contexto público CMS,
sin un segundo router.

`<x-cms::block type="…" :payload="…" />` usa registro, validación, disponibilidad
y lectores públicos compartidos con el render de secciones. Blade puede incluir
noticias vivas y formularios; no tiene caché HTML de página completa por defecto.
La búsqueda nunca ejecuta Blade arbitrario para extraer contenido. Los formularios
conservan CSRF y estado de la petición. Un documento completo personalizado incluye
el componente compartido de privacidad.

Los bloques componibles declaran zonas de hijos en su esquema. El núcleo comparte
recorrido acotado, validación, referencias, disponibilidad, apariencia, render y
extracción semántica; las vistas y consultas permanecen en sus propietarios. La
sección es el layout (columnas y estilos); los bloques son piezas genéricas. Las
plantillas son entidades de admin bajo Páginas (tablas `block_components*`),
sembradas en la instalación y copiadas al insertar; no presets del
editor. Búsqueda y SEO recorren contenido público anidado sin ejecutar Blade; un
acordeón no implica marcado FAQ. Véase
[composición y payload](EXTENSIONS.md#composición-y-payload-de-bloques).

El subsistema Forms del núcleo conserva definición, validación, envíos privados y acciones. La instalación crea contacto editable y operativo, con tratamiento revisado y conservación finita. MCP está disponible con token y permisos desde la instalación, sin emisión automática de tokens. Búsqueda pública no registra términos ni permite indexación por defecto. Estas capacidades no tienen ciclo de instalación de módulo; sus opciones operativas pertenecen a ajustes del sistema. Popups es un
módulo opcional independiente de marketing: aporta contenido, reglas y disparadores,
y reutiliza overlays, foco y consentimiento compartidos. Desactivarlo impide sus
consultas y ejecución. Ni componentes ni editor imponen revisiones editoriales.

## Privacidad por defecto

La instalación recoge datos reales de empresa/responsable y crea aviso legal,
privacidad y cookies localizados mediante vistas Blade reservadas. Los datos
legales tienen una única fuente y las versiones de política describen tratamientos
reales configurados. La referencia suministrada es España/UE:
[RGPD](https://eur-lex.europa.eu/eli/reg/2016/679/oj),
[LOPDGDD](https://www.boe.es/eli/es/lo/2018/12/05/3/con),
[LSSI](https://www.boe.es/eli/es/l/2002/07/11/34/con) y
[guía de cookies AEPD](https://www.aepd.es/guias/guia-cookies.pdf).
La privacidad técnica estricta se aplica a todos los mercados; otras jurisdicciones
requieren adaptación. Las páginas generadas no certifican cumplimiento legal.

Un mecanismo del núcleo gestiona información y consentimiento en navegador y
servidor. Los fines opcionales comienzan denegados. El consentimiento tiene revisión
y caducidad; aceptar, rechazar y retirar son accesibles. Scripts, embeds, posters
y seguimiento externos opcionales esperan autorización; los trabajos vuelven a
comprobarla antes de enviar. Solo el almacenamiento necesario no requiere petición
de consentimiento opcional. La información refleja configuración y almacenamiento
efectivos. No se añaden parsers de cookies por módulo.

Los formularios muestran información contextual y separan marketing opcional.
No se exige consentimiento si corresponde otra base jurídica. Se minimizan datos,
incluidos IP/UA. Cada propietario implementa conservación y derechos autorizados,
incluso para datos retenidos tras desactivar. Se respetan restricciones justificadas
de conservación. No hay motor universal de cumplimiento ni afirmaciones falsas
de anonimato o conformidad.

## Mutaciones y verificación

Mantén autorización, revisión editorial, publicación, idioma, mercado y privacidad
en HTTP, MCP, trabajos y vistas.

Un controlador HTTP valida con el Form Request y delega en el servicio del
dominio. MCP autoriza y valida en `CapabilityExecutor`, después llama al mismo
servicio desde la capacidad. `EntitySchemaRegistryInterface` dirige las
mutaciones de entidad a su esquema y handler. `SectionSyncService` persiste la
composición y `ResourceCrudService` gestiona los recursos que exponen ese
contrato. El servicio del dominio decide el resultado y controla la transacción.
Los efectos externos se ejecutan después del commit. [Convenciones de desarrollo](CONVENTIONS.md)

Los borradores preservan el contenido público. La aprobación comprueba que el
contenido de partida coincide y vincula actor, destino y cambio. Los snapshots
guardan traducciones, secciones y relaciones. La restauración es transaccional.

Usa Composer/npm, Sail oficial y comprobaciones Docker aisladas. Cada repositorio
comprueba sus fuentes y sus integraciones declaradas con sus pruebas. El instalador
CLI `dbcms new` crea Laravel e instala la composición mediante Composer;
`cms:setup` prepara los archivos del proyecto sin acceder a la base de datos.
El asistente web y `cms:install` conservan el aprovisionamiento explícito.
El despliegue pertenece a cada proyecto consumidor.
Las pruebas de instalación usan archivos y bases de datos desechables. Protege
secretos, claves y datos de trabajo. [Pruebas](TESTING.md)
