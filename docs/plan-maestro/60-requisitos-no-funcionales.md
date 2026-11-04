# Requisitos no funcionales

## Propósito y fuentes

Criterios transversales exigidos por la fuente para trazabilidad, operación de depósito y control de inventario. Se verifican junto con los recorridos funcionales afectados; sus dependencias indican secuencia de comprobación, no permiso para postergar seguridad o usabilidad.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: Calidad y plataforma. Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

Confirmados: dos segundos para búsqueda histórica; cinco segundos para reflejar PP/stock entre puestos; concurrencia de todos los actores habilitados; auditoría retenida indefinidamente. Los usuarios y permisos son configurables, incluidos vendedores S/G. El corte usa tipografía de 16 px, controles grandes y captura manual; la validación industrial/lectores sigue pendiente. La auditoría implementa protección SQL y firmas encadenadas; resistir control total del servidor requiere SEC-001 externo.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [PENDIENTE] RNF-001 — Responder búsquedas históricas de lotes y partidas en un máximo de 2 segundos

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RNF 01; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Calidad y plataforma
- **Dependencias:** RF-009
- **Alcance:** Búsqueda histórica que vincula clientes con lotes/partidas, desde el ingreso del parámetro hasta el resultado visible, en un máximo de 2 segundos.
- **Fuera de alcance:** Afirmar cumplimiento a partir de un benchmark vacío o de una consulta SQL aislada.
- **Invariantes:** El máximo es 2 segundos, no una media o un percentil sustituto; medir el recorrido definido y conservar exactitud y permisos.
- **Contratos y archivos previstos:** Propuesta: consultas/índices de trazabilidad y pruebas de rendimiento sobre datos representativos; protocolo y evidencia del entorno de medición.
- **Criterios de aceptación:** Cada consulta del conjunto acordado devuelve resultados correctos y visibles en no más de 2 segundos; se incluyen coincidencias, ausencia de resultados y el historial definido. La evidencia registra volumen, hardware, motor, concurrencia, caché y comienzo/final de la medición.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** al implementar el buscador histórico, medir máximo de dos segundos con todos los actores habilitados y un volumen representativo; las exportaciones reales se harán después
- **Evidencia actual:** Requisito RNF 01 incorporado desde la fuente conservada; sin implementación ni pruebas funcionales específicas observadas. Dependencias, contratos y casos de prueba son descomposición técnica del plan, no decisiones comerciales adicionales.

### [PARCIAL] RNF-002 — Ofrecer interfaz de depósito legible, de alto contraste y operable con lectores

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RNF 02; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Calidad y plataforma
- **Dependencias:** INV-001, RF-011
- **Alcance:** Diseño de depósito con alto contraste, tipografía clara de al menos 14 px, campos legibles, botones grandes e integración ágil con códigos de barras/QR.
- **Fuera de alcance:** Declarar accesibilidad certificada o adoptar umbrales de botones/contraste que no fueron acordados.
- **Invariantes:** El tamaño mínimo exigido es 14 px; mensajes de validación, lotes, partidas y cantidades también deben poder leerse. El lector no debe inducir transcripciones manuales innecesarias.
- **Contratos y archivos previstos:** Propuesta: pantallas/componentes de depósito y adaptador de lectura; comprobaciones de estilos, foco y prueba operativa con el equipo acordado.
- **Criterios de aceptación:** Se verifica tamaño computado mínimo de 14 px en textos operativos y contraste/campos/botones según medidas acordadas; un recorrido de picking con lector funciona y mantiene el foco previsto. Se evalúan códigos largos, errores de lectura y uso en el entorno industrial con representantes del depósito.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** validar operación industrial con representantes y ampliar la verificación a las futuras pantallas/lectores
- **Evidencia actual:** Interfaz inicial de operaciones: tipografía base 16 px, botones de 48 px, contraste alto, campos de lote/fabricación y captura manual. HTML y build verificados; no se declara prueba ergonómica con Claudia ni integración de hardware inexistente.

### [PARCIAL] RNF-003 — Aplicar roles individuales y auditoría no modificable en cada alta y baja de stock

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RNF 03; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Calidad y plataforma
- **Dependencias:** ACC-001, INV-001, SEC-001
- **Alcance:** Gestión configurable de usuarios/roles/permisos, incluidos vendedores S/G; log obligatorio no modificable de altas/bajas con retención indefinida y protección frente a administradores según la arquitectura delegada.
- **Fuera de alcance:** Permisos ligados al nombre, cuentas compartidas o credenciales personales inventadas; afirmar resistencia total al administrador del servidor sin la capa externa SEC-001.
- **Invariantes:** Toda alta/baja registra fecha, hora, identidad del usuario y cantidad de pares; un rechazo de permisos no altera stock. La operación y su registro deben mantener consistencia y el historial no permite edición/borrado mediante los accesos cubiertos por el modelo de protección acordado.
- **Contratos y archivos previstos:** Propuesta: contratos de identidad/autorización en el núcleo y auditoría de movimientos; adaptadores de autenticación, persistencia y protección del historial; pruebas por rol.
- **Criterios de aceptación:** Las pruebas de cada rol permiten acciones asignadas y rechazan las restantes tanto por UI como acceso directo; cada alta/baja válida deja los cuatro datos obligatorios. Se rechazan modificación/borrado del historial y operaciones sin usuario; un fallo de auditoría no permite un cambio de stock definitivo sin registro. Se prueba la protección privilegiada conforme al modelo de amenaza definido.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** desplegar y verificar SEC-001; mantener controles de autorización y auditoría en cada recorrido futuro
- **Evidencia actual:** Se implementan roles configurables, denegación en servidor, bloqueo de quitar el último administrador, movimientos+auditoría atómicos, tablas append-only, HMAC encadenado y verificador. Las pruebas detectan manipulación aun eliminando un disparador. Sin copia/cierre externo no se acredita resistencia a truncamiento/restauración por control total del servidor; por eso permanece PARCIAL.


### [PENDIENTE] SEC-001 — Desplegar evidencia externa inmutable y recuperación de auditoría

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Seguridad e infraestructura
- **Dependencias:** INV-001
- **Alcance:** Copias/cierres firmados fuera del servidor de fábrica, almacenamiento inmutable y credenciales separadas; retención indefinida y recuperación verificable.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Un administrador del servidor no puede destruir también la evidencia externa; un rollback/truncamiento se detecta contrastando el último cierre conservado. Verificar restauración y protección/custodia de claves históricas. No declarar esta infraestructura operativa sin una prueba real.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** definir y desplegar el destino externo y las credenciales separadas cuando se prepare la operación; las firmas locales ya sirven como base
- **Evidencia actual:** Trabajo registrado a partir de SRS-ACL-2026-11-04; sin implementación de este módulo todavía.
