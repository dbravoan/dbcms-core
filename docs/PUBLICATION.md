# Publicar core

1. Integra el candidato en `main` y actualiza el changelog antes de validar el SHA que quieres publicar.
2. Ejecuta `composer check`, `composer test` y `composer test:dist` en el entorno desechable. Comprueba que el ZIP incluye los contratos de `docs/` y los stubs de `cms:setup`. [Pruebas](TESTING.md)
3. Crea y sube un tag anotado nuevo para ese SHA. La versión de cada paquete expresa su propio ciclo y las dependencias declaran sus rangos en Composer.
4. Cuando Packagist indexe el tag, resuelve esa versión exacta en una aplicación limpia y comprueba instalación y arranque.
