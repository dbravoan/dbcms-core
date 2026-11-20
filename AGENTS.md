# Trabajar en dbCMS Core

Lee [arquitectura](docs/CORE_AND_EXTENSIONS.md), [convenciones](docs/CONVENTIONS.md)
y los [contratos de extensiones](docs/EXTENSIONS.md) afectados. Son las fuentes
canónicas para humanos y agentes.

PHP, Composer, Artisan y Pest se ejecutan en contenedores. Usa aplicaciones y bases
de datos desechables; conserva configuración, claves y datos de trabajo.
Los comandos están en [desarrollo](docs/DEVELOPMENT.md) y [pruebas](docs/TESTING.md).

Antes de implementar, sigue el punto de entrada hasta su propietario y busca
servicios, esquemas, bloques o componentes del kit que cubran el caso. Usa la
[tabla de reutilización](docs/CONVENTIONS.md#buscar-y-reutilizar-antes-de-implementar)
y amplía el patrón existente en su propietario si hace falta. Conserva sus
validaciones y verifica el cambio desde la entrada real.

Mantén las guías en presente y enlaza el contrato propietario. La
[publicación](docs/PUBLICATION.md) pertenece a este repositorio.
