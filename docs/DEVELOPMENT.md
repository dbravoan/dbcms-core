# Desarrollo de core

Trabaja desde la raíz de Core. Docker Compose levanta `tools` y un MySQL
desechable. `composer prepare` construye la aplicación Laravel de pruebas.

```bash
docker compose run --build --rm tools composer prepare
docker compose run --rm tools composer check
docker compose run --rm tools composer test
docker compose run --rm tools composer test:dist
docker compose down --volumes --remove-orphans
```

`tests/package.php` define la composición y `tests/fixture/` fija sus
dependencias. Regenera los locks con `composer fixture:lock` cuando cambias
las dependencias y revisa el diff. Los paquetes mínimos de `tests/Fixtures/`
prueban contratos de terceros.

Para integrar un cambio de Core en una web de prueba, registra el checkout
como repositorio Composer `path` en esa web, resuelve su lock y recompila sus
assets. [Instalador](https://github.com/dbravoan/dbcms-installer)
