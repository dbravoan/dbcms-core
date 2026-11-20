# Contribuir

Cada producto se desarrolla en su propio repositorio. Para core, consulta
[arquitectura](docs/CORE_AND_EXTENSIONS.md) y [convenciones](docs/CONVENTIONS.md).
Prepara la aplicación desechable y ejecuta `composer test`, `composer check` y
`composer test:dist` dentro del contenedor descrito en
[desarrollo](docs/DEVELOPMENT.md).

Añade pruebas del comportamiento cambiado y actualiza su contrato público.
Integra en `main` una vez revisado y publica un tag nuevo únicamente con calidad
correcta del SHA exacto. Sigue [publicación](docs/PUBLICATION.md).

Para módulos, temas o bloques, sigue los [contratos de extensiones](docs/EXTENSIONS.md)
y parte de una contribución equivalente al caso.
