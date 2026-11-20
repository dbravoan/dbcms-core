# Pruebas de core

Ejecuta los comandos de [desarrollo](DEVELOPMENT.md) sobre archivos y MySQL
desechables. La suite propia cubre permisos, publicación, localización, privacidad,
ejecución, instalación y extensiones mediante fixtures mínimos.

- `test`: Pest, arquitectura y comportamiento frontend.
- `check`: formato, PHPStan nivel 8, dependencias y compilación Vite real.
- `test:dist`: instalación del ZIP sin checkout y comprobación de rutas, admin y bloques nativos.

Las regresiones específicas de módulos y temas pertenecen a sus repositorios.
Las pruebas de navegador en `tests/Browser` apuntan a una aplicación
desechable. Define `DBCMS_BROWSER_BASE_URL` y, para pantallas privadas,
`DBCMS_BROWSER_STORAGE_STATE`. Desde Core instala las dependencias con `npm ci`
y ejecuta `npx playwright test --config tests/Browser/playwright.config.mjs <spec>`.
Elige Chromium con `npx playwright install chromium` o indica el ejecutable en
`DBCMS_BROWSER_CHROME`.
Para `install-wizard.spec.mjs`, indica `DBCMS_BROWSER_INSTALLER=1` y el
código de un solo uso de esa aplicación mediante `DBCMS_BROWSER_INSTALL_TOKEN`.
Los [cuatro recorridos de landings con Rich Text](HOW_TO_RICH_TEXT_LANDINGS.md) documentan cómo reproducir las capturas desde backoffice y MCP.
Las llamadas a proveedores se simulan; una comprobación real se informa aparte.

Consulta [Desarrollo](DEVELOPMENT.md) para preparar fixtures y locks. Al informar
resultados, indica la revisión, los comandos y el alcance de la ejecución.
