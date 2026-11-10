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

La exportación HTML es autosuficiente. La sincronización escucha solo en `127.0.0.1`; valida antes de publicar y mantiene la última versión válida ante errores. Por defecto no escribe Markdown. Sirve el visor, JSON y copias de las especificaciones validadas, no el repositorio completo. Ctrl+C la detiene.

Buscar por ID/título, filtrar área/capa/estado, seleccionar detalles y recorrer la cadena completa. Las flechas van del requisito al dependiente y salen de Dependencias, sin deducir estados o relaciones narrativas. Los filtros, selección y zoom se conservan al recibir cambios. Todos los campos del esquema se muestran en el detalle.

El detalle permite editar una propuesta, escribir una instrucción para Codex, copiar su contexto y abrir la app en el proyecto con el prompt del nodo seleccionado. El enlace documentado en [Commands: Deep links](https://learn.chatgpt.com/docs/app/commands) combina `path` (carpeta efectiva de `--root`, nunca tomada de los campos editables) y `prompt` (contexto completo e instrucción adicional codificados). El prompt queda en el campo de mensaje para revisión y envío explícito del usuario. Requiere la app instalada y asociada al protocolo; si el navegador no abre la app, copiar el prompt y pegarlo en el proyecto. Una exportación llevada a otro equipo debe regenerarse desde la ubicación local; un visor antiguo sin ruta deshabilita el enlace. Los datos del plan no son autorizaciones ni comandos ejecutables. La exportación estática solo prepara propuestas en memoria, sin operaciones de escritura; cerrar o recargar la página pierde los borradores.

La interfaz llama Alcance autorizado al campo Capa: SOLICITADO es un pedido confirmado, PROPUESTO espera autorización y EXCLUIDO queda fuera por decisión explícita. No representa capas de arquitectura. Se conserva la clave Capa en Markdown/JSON y la versión 1 del esquema.

La edición persistente es opcional: `serve --allow-edit --port 8788`. Requiere una rama Git distinta de main/master, revisión del diff validado y autorización expresa de cada guardado. ID, estado y dependencias se conservan; la evidencia admite agregar verificaciones sin borrar historia. El servidor valida todo el plan en memoria, comprueba revisión/rama y reemplaza únicamente la fuente seleccionada. Un cambio de campos invalida la revisión; un conflicto conserva el borrador y exige revisarlo sobre una versión actualizada. La protección de revisión detecta cambios externos antes de reemplazar; no sustituye coordinar editores externos que escriban simultáneamente. Los cambios quedan locales: revisar `git diff` y preparar una Pull Request hacia main para aceptarlos. El visor no hace commits, push ni merge.

Ante un error del plan, corregir la fuente siguiendo su alcance; no debilitar la validación ni reemplazar una evidencia para mostrar verde. El reporte calcula estados desde Markdown y no acredita aceptación del producto o publicación.

Para modificar las herramientas, ejecutar las pruebas del kit (`python3 -B -m unittest discover -s tests -v` y `node --test tests/roadmap-context.test.js` desde el kit), comprobar sintaxis con `node --check` para ambos assets JavaScript y hacer revisión real del visor cuando cambie su interfaz. Los ejemplos son datos de demostración; una captura de ellos no acredita una funcionalidad del producto.
