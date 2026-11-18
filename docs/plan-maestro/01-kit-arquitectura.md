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

### [HECHO] GIT-005 — Publicar por separado el cambio previo de package-lock.json

- **Capa:** SOLICITADO
- **Fuente:** solicitud de publicar las ramas y PR del 2026-11-18; respuesta expresa del usuario «Publicarlo en una PR separada» ante la consulta sobre package-lock.json.
- **Contexto propietario:** coordinación Git y dependencias frontend
- **Dependencias:** ninguna
- **Alcance:** recuperar únicamente el package-lock.json previamente preparado en el stash, registrar su comprobación y publicarlo en una rama y PR independientes.
- **Fuera de alcance:** actualizar versiones adicionales, corregir o instalar dependencias por inferencia, mezclar el kit o el presupuesto e integrar la PR.
- **Invariantes:** conservar exactamente el cambio recibido, package.json, la aplicación y el stash; los fallos de consistencia deben quedar explícitos en la PR.
- **Contratos y archivos previstos:** package-lock.json; esta tarea y el snapshot del plan; rama codex/package-lock-pendiente y PR.
- **Criterios de aceptación:** archivo idéntico al cambio preparado en el stash, comprobación de consistencia registrada, rama publicada y PR abierta contra main; si la comprobación falla o queda incompleta, PR en borrador con el límite documentado.
- **Responsable:** codex-publicacion-01a1f00b
- **Fecha de inicio:** 2026-11-18
- **Última actualización:** 2026-11-18
- **Próximo paso:** publicación completada; PR #6 en borrador para revisar el lockfile y corregir/verificar npm ci antes de su integración. Esta tarea no acredita aceptación funcional del lockfile.
- **Evidencia actual:** recuperado exactamente de 4bf0ead^2:package-lock.json; SHA-256 8f80eab8d857da707fe1b3fe4410e2babb736dcaa9cbf36713c081428fa4efcf. El diff modifica el nombre del proyecto, retira React y metadatos libc de paquetes Linux y agrega dos marcas peer. Con Node v24.11.0 y npm 11.6.2, npm ci --dry-run --ignore-scripts --offline --no-audit --no-fund falla con EUSAGE: package.json y package-lock.json no están sincronizados, Missing: react@ from lock file; ink exige React >=19.2.0. No se instalaron paquetes ni ejecutaron scripts. Se conserva el cambio original y package.json permanece intacto; corresponde revisión técnica antes de integrarlo.
  - Rama codex/package-lock-pendiente publicada en personal y PR [#6](https://github.com/stefanoreichert/Seminario-SIMON-SRL/pull/6) abierta como borrador contra main del repositorio principal. Identidad del archivo, validación documental y git diff --check comprobados. Se conserva el stash y no se mezcla esta entrega con las PR #4/#5.
