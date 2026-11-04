# Kit de arquitectura y herramientas del plan

### [HECHO] KIT-001 — Integrar el prompt maestro y kit reutilizable de arquitectura hexagonal y DDD

- **Capa:** SOLICITADO
- **Fuente:** solicitud del usuario del 2026-11-04; wj-004/Promt-AI commit e073de35c24e58ba79a9dd376cd610e5f5a21e41; DEC-2026-11-04-01 y DEC-2026-11-04-02
- **Contexto propietario:** herramientas de desarrollo y arquitectura del proyecto
- **Dependencias:** ninguna
- **Alcance:** prompt maestro portable, instalador, tres skills locales, perfiles base/Laravel/Flutter/Go, plantillas, ficha, arquitectura, decisiones, esquema común, validador, reportes, visor autónomo y sincronización local
- **Fuera de alcance:** funcionalidades de negocio, migraciones, nuevas dependencias de producto e integración del visor en el menú de la aplicación
- **Invariantes:** conservar las reglas Laravel y cambios existentes; un único esquema versión 1; plan tratado como datos; ninguna sobrescritura automática de archivos diferentes; servidor solo en loopback y última versión válida ante errores
- **Contratos y archivos previstos:** AGENTS.md; README.md; resources/kit-inicio-proyecto; .agents/skills y metadatos del proyecto; docs/proyecto.md; docs/arquitectura; docs/decisiones; docs/plantillas; docs/plan-maestro
- **Criterios de aceptación:** tres skills válidas; instalación portable e idempotente sin sobrescrituras; rechazo de planes inválidos, ciclos y dependencias inexistentes; exportación autónoma con todos los campos; servidor recupera cambios válidos y rechaza acceso a otros archivos; visor comprobado en navegador; ZIP con manifiesto; requisitos pendientes documentados
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** utilizar las skills y el visor con los requisitos recibidos en SRS-2026-11-04; continuar las definiciones de INI-002 e INI-003. Para otro proyecto, usar el prompt y el kit portable siguiendo su README.
- **Evidencia actual:** origen fijado al commit indicado; AGENTS.md conciliado; correcciones Windows de reserva exclusiva de puerto y enlaces exportados.
  - python -X utf8 -B -m unittest discover -s resources/kit-inicio-proyecto/tests -v: 39 casos, 36 aprobados y 3 omitidos por WinError 1314 al crear enlaces simbólicos; se conserva este límite de verificación.
  - php artisan test: 2 pruebas y 2 aserciones aprobadas; solo comprueban el esqueleto existente.
  - Validador instalado: plan válido, esquema 1, cuatro tareas y dos dependencias; exportación HTML autónoma generada desde el mismo parser. node --check aprobado para el JavaScript original y el instalado.
  - Tres SKILL.md e interfaces openai.yaml validados con las reglas de quick_validate.py y el parser Symfony YAML 8.1.10 ya instalado; PyYAML no estaba disponible y no se agregó ninguna dependencia.
  - Navegador sobre servidor en 127.0.0.1 con puerto asignado: búsqueda INI-002 devuelve una tarea; detalle conserva los catorce campos; cadena completa muestra INI-001 → INI-002 → INI-003; salir restaura la búsqueda; filtro de estado y zoom comprobados. La suite verifica conservación y recuperación de la última versión válida y restricciones HTTP.
  - storage/app/kit-inicio-proyecto.zip: 32 recursos y MANIFEST.sha256; todos sus hashes comprobados. Los 17 recursos de las skills instaladas coinciden con la fuente reutilizable.
  - Al cierre inicial de esta integración, los requisitos, contextos y plan funcional permanecían pendientes por DEC-2026-11-04-03. Después se recibió SRS-2026-11-04 y DEC-2026-11-04-04 actualizó esa postergación; las especificaciones registran el nuevo alcance. Este cierre histórico no acredita funcionalidades comerciales.
