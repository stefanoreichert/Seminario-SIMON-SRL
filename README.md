# Seminario SIMON SRL

Proyecto desarrollado con Laravel 13, PHP 8.3 o superior y Vite.

## Instalación

Requiere PHP, Composer y Node.js.

```bash
composer run setup
```

El comando prepara `.env`, genera la clave, ejecuta las migraciones e instala y compila el frontend. Por defecto se utiliza SQLite.

## Desarrollo

```bash
composer run dev
```

## Pruebas

```bash
php artisan test
```

## Kit de arquitectura y plan maestro

Preparación basada en [wj-004/Promt-AI actualizado](https://github.com/wj-004/Promt-AI/tree/04f621f12421e819bfeb1cc1518c4e864617d14e), adaptada a este repositorio y Windows. Requiere Python 3.9 o posterior y su biblioteca estándar; no agrega dependencias de la aplicación.

- [Prompt maestro reutilizable](resources/kit-inicio-proyecto/PROMPT_MAESTRO.md) y [guía del kit](resources/kit-inicio-proyecto/README.md).
- [Ficha del proyecto](docs/proyecto.md), [arquitectura](docs/arquitectura/README.md) y [decisiones](docs/decisiones/README.md).
- [Plan maestro](docs/plan-maestro/README.md) y [visor HTML sin conexión](docs/plan-maestro/visualizacion.html).
- Skills locales: [arquitectura](.agents/skills/arquitectura-hexagonal-ddd/SKILL.md), [planificación](.agents/skills/plan-maestro-desarrollo-sistema/SKILL.md) y [visor/validador](.agents/skills/manage-project-roadmap/SKILL.md).

Desde la raíz del proyecto:

```sh
python -X utf8 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py check
python -X utf8 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py report --json
python -X utf8 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py tree
python -X utf8 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py serve --port 8788
```

Abrir `http://127.0.0.1:8788/visualizacion.html` mientras `serve` esté ejecutándose. Ctrl+C detiene la sincronización; `--port 0` elige un puerto libre. Para una copia autónoma, ejecutar `tree` y abrir el HTML generado. El visor permite buscar, filtrar, leer los catorce campos y recorrer dependencias; conserva la última versión válida ante errores del plan.

Para reutilizar el kit en otro proyecto, revisar primero los destinos con `--dry-run`; el instalador rechaza archivos diferentes, incluido un `AGENTS.md` existente:

```sh
python -X utf8 -B resources/kit-inicio-proyecto/scripts/init_project.py --target /ruta/al/proyecto --name "Mi proyecto" --profile base --dry-run
python -X utf8 -B resources/kit-inicio-proyecto/scripts/package_kit.py --output storage/app/kit-inicio-proyecto.zip
python -X utf8 -B -m unittest discover -s resources/kit-inicio-proyecto/tests -v
node --check resources/kit-inicio-proyecto/skills/manage-project-roadmap/assets/roadmap-tree.js
```

Repetir el primer comando sin `--dry-run` para instalar en el destino revisado. El ZIP incluye un manifiesto SHA-256 y puede adjuntarse junto al prompt. El perfil `base` mantiene el stack por definir; también existen perfiles Laravel, Flutter y Go.

Los requisitos aportados por el usuario (RF01–RF17 y RNF 01–RNF 03) están incorporados al [plan maestro](docs/plan-maestro/README.md), con [fuente, trazabilidad y definiciones abiertas](docs/plan-maestro/02-requisitos-y-alcance.md). El mapa de contextos es preliminar; las tareas funcionales permanecen pendientes y se implementarán cuando se solicite su ejecución.

## Primer flujo de fábrica

Se incorporan gestión de usuarios/roles/permisos y operaciones en `/gestion/usuarios` y `/gestion/operaciones`, accesibles desde el panel con los permisos correspondientes. La [guía del corte](docs/plan-maestro/03-decisiones-relevamiento.md) conserva aclaraciones, alcance y arranque por etapas.

```sh
php artisan migrate
php artisan access:bootstrap correo-del-administrador-existente
php artisan inventory:audit-verify
```

El bootstrap se usa una sola vez con una cuenta existente; después se asignan roles y cuentas desde el módulo. Si todavía no existe una cuenta de acceso, el comando existente `php artisan make:filament-user` permite crearla de forma interactiva. No se incluyen contraseñas ni usuarios personales precargados.

Las operaciones requieren lote y fabricación, autorizaciones, permisos y una clave de firma disponible. `INVENTORY_AUDIT_KEY` permite una clave dedicada; si se deja vacía se utiliza APP_KEY. Respaldar las claves históricas y desplegar la evidencia externa inmutable de SEC-001 antes de afirmar protección frente al control total del servidor.

El saldo de mercadería disminuye al despachar; la deuda monetaria no se cancela. No se importan datos reales ni se generan formularios fiscales, hojas de ruta o reportes de módulos pospuestos en este corte.

## Ubicación de los contextos

El código propio vive en `src/<Contexto>/{Domain,Application,Infrastructure,UI}` y usa `Simon\\` mediante Composer. Acceso, ClientesAdministracion, InventarioTrazabilidad, VentasPedidos y Shared están allí. `App\\` conserva la composición técnica. [Arquitectura y descubrimiento de migraciones](docs/arquitectura/README.md) y [registro del traslado](docs/plan-maestro/04-traslado-contextos-src.md).

Las vistas propias y activos de operaciones también viven bajo UI/Views y UI/Assets de src. AppServiceProvider registra las vistas; las entradas de Vite en resources importan los activos del contexto.

La migración de operaciones conserva su nombre bajo `src/Shared/Infrastructure/Database/Migrations`; una instalación existente la reconoce como aplicada. Tras actualizar un checkout, ejecutar `composer dump-autoload --optimize --strict-psr --no-scripts` y limpiar cachés de configuración/rutas/vistas. Las pruebas de regresión cubren descubrimiento, factory/autenticación y ausencia de una segunda aplicación de esa migración.
