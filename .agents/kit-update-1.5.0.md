# Actualización del kit a 1.5.0

- Fecha: 2026-11-10 (America/Asuncion).
- Autorización: pedido expreso del usuario de actualizar este proyecto, respaldar y validar antes de modificar, conciliar las tres skills y preservar los datos existentes sin releer los requerimientos originales.
- Origen fijo: [wj-004/Kit-AI, commit f2e8c0ea3cded3fe40eeac64979f0e86bf77d3c7](https://github.com/wj-004/Kit-AI/tree/f2e8c0ea3cded3fe40eeac64979f0e86bf77d3c7/kit-inicio-proyecto). Su README y el mensaje del commit identifican la versión 1.5.0; no había una release publicada.
- ZIP de esa revisión, SHA-256: `13ea5454b0f9b654ef769411eb63e8368ef50b1df5a098297cd0b041aa6aca46`. La extracción se comprobó contra rutas externas y enlaces simbólicos.
- Instalación activa: `.agents/skills/`; se incorporaron los 20 archivos completos de las tres skills, incluidas referencias, perfiles, metadatos, scripts y assets. Se agregó una referencia con las reglas Laravel originales.

## Respaldo y estado previo

Antes de modificar AGENTS o las skills se copiaron íntegros `AGENTS.md`, `.agents/` y `docs/` a [storage/app/backups/kit-1.5.0-20261110-180055](../storage/app/backups/kit-1.5.0-20261110-180055/). Se verificaron por SHA-256 sus 48 archivos. El respaldo conserva los cambios locales previos, el estado de Git, el diff del árbol y del índice, y las salidas/códigos de la validación anterior.

Comando previo: `python -X utf8 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py check`, salida 0: plan válido, esquema 1, 34 tareas. Estados: HECHO=16, PARCIAL=5, PENDIENTE=13. El reporte JSON completo quedó en `plan-report-before.json` dentro del respaldo; no se abrieron los requerimientos originales.

## Conciliación

AGENTS.md adopta la entrada breve y el protocolo de consulta por tarea de 1.5.0. Conserva los comandos Windows, rutas, reglas de permisos y autorizaciones, esquema 1, continuidad de las RF una por vez, estructura `src/<Contexto>/{Domain,Application,Infrastructure,UI}`, prefijo Composer `Simon\\`, reserva de `App\\` para composición y gestión integrada a Filament. Las reglas Laravel/Boost/PHP anteriores se preservan literalmente en [reglas-laravel-proyecto.md](skills/arquitectura-hexagonal-ddd/references/reglas-laravel-proyecto.md), con consulta obligatoria desde AGENTS para cambios PHP/Laravel/UI.

Las tres entradas SKILL.md conservan las instrucciones breves de 1.5.0 y agregan únicamente sus particularidades locales: Windows/UTF-8, rutas y Filament, autorizaciones y preservación del proyecto. Las referencias nuevas contienen el detalle bajo consulta. No se activan las tres skills por cada implementación corriente ni se releen fuentes por rutina.

Se retuvieron dos correcciones locales del visor: reserva exclusiva de puertos Windows mediante SO_EXCLUSIVEADDRUSE y URLs relativas con separadores portables. La suite detectó además separadores Windows en las nuevas salidas de task; se extendió esa misma normalización a sourcePath y planIndex. Las pruebas originales de esos contratos pasan; no se cambió el plan para resolverlo.

## Conservación comprobada

Los manifiestos `.agents/project.json` y `.agents/project-kit.json` permanecen byte a byte intactos. Este registro informa la actualización sin regenerarlos: la versión 1.1.0 que conserva el manifiesto pertenece a su instalación histórica. Se conservan la fecha inicial 2026-11-04, `source_root: src`, el perfil Laravel, la etapa y el prefijo existente. No se ejecutó el instalador sobre este proyecto, no se movieron carpetas ni se reinició la preparación.

También se conserva íntegra la copia histórica `resources/kit-inicio-proyecto/`; no representa la versión de las skills activas. No se modificaron planes, estados, evidencias, fuentes, decisiones, configuraciones ni código del producto, incluidos los cambios locales que ya existían. El HTML previo `docs/plan-maestro/visualizacion.html` permanece intacto; la exportación nueva se hizo con --output dentro del respaldo.

| Comprobación | Resultado |
| --- | --- |
| SHA-256 de 239 archivos protegidos, antes/después | Sin diferencias ni archivos ausentes |
| Diff protegido de Git, respecto del estado previo | Idéntico; SHA-256 `0879beaefc8ba87c9f1bc09320ba4ee0e6979fba76776975902c19f1eae8d41f` |
| Índice de Git | Idéntico; no se agregaron archivos al staging |
| Integridad del respaldo original | 48 archivos coinciden con sus hashes iniciales |
| Esquema JSON del plan | Byte a byte idéntico, versión 1 y catorce campos |
| Reportes previo/nuevo | Mismas 34 tareas, valores previos, IDs, campos, estados, evidencias y aristas de dependencias |

Evidencia auditable en el respaldo: `backup-sha256.json`, `protected-before-sha256.json`, `protected-after-sha256.json`, `preservation-check.json`, `git-diff-before.patch`, `protected-diff-after.patch`, `git-index-before.patch` y `kit-update.diff`. Estos inventarios de verificación no reemplazan ni regeneran los manifiestos históricos.

## Verificación nueva y límites

Con las herramientas instaladas pasaron check, report --json, report --status EN_CURSO --summary --json, task RF-009 en formatos json/context y tree con --output dentro del respaldo. El plan nuevo conserva: HECHO=16, PARCIAL=5, PENDIENTE=13. No se detectaron incompatibilidades del plan y no se reescribió ningún archivo de docs/.

Se ejecutó la suite existente de 1.5.0 contra una copia de las skills realmente instaladas en `validation-kit/`, dentro del respaldo: `python -X utf8 -B -m unittest discover -s tests -v`. Resultado: 63 pruebas, OK, con 9 omisiones de casos/subcasos que requieren privilegio de enlaces simbólicos Windows (WinError 1314). Se conservaron UTF-8 explícito y límites de 30 segundos en subprocesos; la comprobación de archivos no UTF-8 se separó del caso de enlaces para que se ejecute aunque falte ese privilegio. No se debilitaron las aserciones. El instalador se ejercitó únicamente en proyectos temporales de las pruebas, nunca sobre este proyecto.

La primera ejecución falló por permisos del temporal del sandbox. Tras ubicar los temporales dentro del respaldo, quedaron errores HTTP por restricción de loopback y tres fallos de separadores de task. Se conservaron ambos logs fallidos. La ejecución final, con loopback habilitado y la adaptación de rutas, terminó OK; salida completa en `tests-1.5.0.log`. La sintaxis de roadmap-tree.js pasó con node --check, salida 0.

Se inició una revisión del visor con un servidor temporal en puerto libre y salida separada. La integración de navegador agotó el tiempo; el uso de computadora de Windows se detuvo porque no pudo determinar con confianza la URL del navegador. No se continuó la automatización de UI: la revisión visual queda sin acreditar. El servidor temporal se detuvo. Las pruebas HTTP y de exportación no sustituyen esa revisión visual ni la aceptación funcional del producto.
