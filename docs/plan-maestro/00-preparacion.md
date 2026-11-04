# Preparación de Seminario SIMON SRL

El kit y la base de arquitectura se instalaron antes de recibir el alcance funcional. El usuario postergó inicialmente los requisitos (DEC-2026-11-04-03) y luego aportó SRS-2026-11-04 para incorporarlos al plan (DEC-2026-11-04-04). Se conserva esta evolución y los IDs de las tareas iniciales. Los requisitos de producto están en las especificaciones por área; este archivo registra el avance documental.

## Tareas

### [HECHO] INI-001 — Relevar requisitos, fuentes y stack

- **Capa:** SOLICITADO
- **Fuente:** solicitud de preparación y posterior incorporación de SRS-2026-11-04 del 2026-11-04; DEC-2026-11-04-04
- **Contexto propietario:** plataforma y definición del producto
- **Dependencias:** ninguna
- **Alcance:** relevar objetivo, actores, procesos, restricciones y decisiones tecnológicas confirmadas
- **Fuera de alcance:** implementar funcionalidades o elegir un stack por inferencia
- **Invariantes:** distinguir solicitud, propuesta, exclusión y evidencia; conservar decisiones confirmadas y la fuente original
- **Contratos y archivos previstos:** docs/proyecto.md; docs/decisiones; docs/plan-maestro/02-requisitos-y-alcance.md y fuente original
- **Criterios de aceptación:** fuentes identificadas, stack consultado, requisitos conocidos y preguntas materiales registradas sin inventar respuestas
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** mantener la trazabilidad y resolver las definiciones DEF-01–DEF-14 con las áreas correspondientes al precisar cada recorrido
- **Evidencia actual:** Inicialmente el usuario postergó las definiciones por DEC-2026-11-04-03. Posteriormente aportó SRS-2026-11-04: se conservó el adjunto íntegro y su SHA-256, se registraron RF01–RF17 y RNF 01–RNF 03, actores mencionados y preguntas materiales. Se contrastó app/routes/database/tests y php artisan route:list --except-vendor: esqueleto sin implementación comercial. composer show --direct confirma Laravel 13.39.0, Filament 5.11.0 y Livewire 4.4.8; se conserva el stack ya observado. Este cierre acredita relevamiento documental, no funcionalidades ni validación legal.

### [HECHO] INI-002 — Definir contextos, datos y contratos de arquitectura

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; respuestas del usuario del 2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** plataforma y contextos identificados durante el relevamiento
- **Dependencias:** INI-001
- **Alcance:** definir lenguaje, fronteras, propiedad de datos y contratos, adaptando las capas al stack confirmado
- **Fuera de alcance:** construir servicios, instalar frameworks o suponer microservicios
- **Invariantes:** dependencias hacia el núcleo, dominio independiente, escrituras por casos de uso y contratos publicados entre propietarios de datos
- **Contratos y archivos previstos:** docs/arquitectura/README.md, contratos propuestos en especificaciones y decisiones relacionadas
- **Criterios de aceptación:** contextos justificados por procesos y reglas, contratos y dependencias explícitos y decisiones abiertas identificadas
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** conservar los contratos del corte autorizado y revisar los módulos expresamente pospuestos cuando se soliciten
- **Evidencia actual:** Tras la fase PARCIAL inicial, las respuestas fijaron roles configurables, vendedores S/G, CUIT, validación manual, lote/fabricación, baja de proceso al alta, parciales y saldos separados. Se definieron puertos AccessPolicy, InventoryStore, UnitOfWork, OrderFulfillment, DispatchAuthorization y repositorios de clientes/pedidos, conectados en AppServiceProvider; Domain/Application no dependen de Laravel ni de adaptadores concretos. Las incógnitas de formularios, reportes e importaciones quedan acotadas a módulos futuros; no impiden el corte autorizado. Ver docs/arquitectura/README.md y 03-decisiones-relevamiento.md. Actualización REF-001/DEC-2026-11-04-06: ubicación src, namespace Simon y registro explícito de modelo, comandos y migraciones; se conserva esta evidencia histórica del corte.

### [HECHO] INI-003 — Consolidar un plan ejecutable con su visor

- **Capa:** SOLICITADO
- **Fuente:** solicitud de preparar plan maestro y visor y posterior incorporación de SRS-2026-11-04 del 2026-11-04
- **Contexto propietario:** planificación del proyecto
- **Dependencias:** INI-002
- **Alcance:** descomponer requisitos confirmados, propuestas y definiciones en tareas con criterios, dependencias y evidencia
- **Fuera de alcance:** comenzar desarrollo de producto por el solo hecho de listar tareas
- **Invariantes:** esquema común, IDs estables, fuente íntegra y correspondencia entre archivos, parser y visor
- **Contratos y archivos previstos:** docs/plan-maestro, registro de requisitos/fuentes, especificaciones de cinco áreas, requisitos no funcionales y exportación HTML
- **Criterios de aceptación:** requisitos conocidos cubiertos por tareas, decisiones pendientes visibles, plan válido y visor generado sin atribuir entregas inexistentes
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** mantener evidencia y dependencias por tarea; iniciar módulos pospuestos solo con su alcance correspondiente
- **Evidencia actual:** Plan consolidado con fuente original, aclaraciones y decisión DEC-2026-11-04-05. Mantiene los veinte IDs RF/RNF y agrega ACC-001, INV-001, FIN-001, LOG-001, DAT-001, CAN-001 y SEC-001. Se corrigieron criterios de historial crediticio, necesidades de producción e ID individual; se separó base de permisos/auditoría de verificación integral para evitar dependencia de aceptación circular con movimientos. Validación estructural y exportación del mismo plan; módulos pospuestos y limitaciones conservados sin atribuir funcionalidades no implementadas.
