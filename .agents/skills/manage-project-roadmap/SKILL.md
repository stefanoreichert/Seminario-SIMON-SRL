---
name: manage-project-roadmap
description: Consultar, validar y visualizar el plan maestro del proyecto con reportes y un árbol interactivo de tareas y dependencias. Usar para comprobar el esquema, explorar avances, exportar el visor o sincronizar cambios locales; el plan y su metodología pertenecen a la skill hermana de planificación.
---

# Herramientas del plan maestro

Leer el contrato del proyecto y el índice de `docs/plan-maestro`. Para crear o modificar requisitos/tareas, usar la skill hermana [plan-maestro-desarrollo-sistema](../plan-maestro-desarrollo-sistema/SKILL.md). Su [esquema versionado](../plan-maestro-desarrollo-sistema/references/plan-schema.json) es la única definición de campos y estados; el parser y el visor lo comparten.

Desde la raíz del proyecto:

```sh
python3 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py check
python3 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py report --status EN_CURSO
python3 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py report --json
python3 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py tree
python3 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py serve --port 8788
```

Los comandos aceptan `--root` para otro proyecto y `--plan-dir` para otro plan. `tree` y `serve` admiten `--output`; `serve` admite puerto e intervalo. Con `--port 0` el sistema asigna un puerto libre y el comando imprime la URL real. Identificar el proyecto mediante `projectId` en `/__roadmap__/status` antes de reutilizar un servicio; no iniciar observadores duplicados ni detener uno ajeno para liberar un puerto.

La exportación HTML es autosuficiente. La sincronización escucha solo en `127.0.0.1`; valida antes de publicar, mantiene la última versión válida ante errores y no escribe Markdown. Sirve el visor, JSON y copias de las especificaciones validadas, no el repositorio completo. Ctrl+C la detiene.

Buscar por ID/título, filtrar área/capa/estado, seleccionar detalles y recorrer la cadena completa. Las flechas van del requisito al dependiente y salen de Dependencias, sin deducir estados o relaciones narrativas. Los filtros, selección y zoom se conservan al recibir cambios. Todos los campos del esquema se muestran en el detalle.

Ante un error del plan, corregir la fuente siguiendo su alcance; no debilitar la validación ni reemplazar una evidencia para mostrar verde. El reporte calcula estados desde Markdown y no acredita aceptación del producto o publicación.

Para modificar las herramientas, ejecutar las pruebas del kit y revisión real del visor cuando cambie su interfaz. Los ejemplos son datos de demostración; una captura de ellos no acredita una funcionalidad del producto.
