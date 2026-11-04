# Depósito, stock y trazabilidad

## Propósito y fuentes

Este contexto conserva pares identificables, lotes, partidas y movimientos físicos. Una consulta de necesidades combina sus existencias con PP y producción en proceso a través de contratos de sus propietarios; no transforma esa proyección en un movimiento físico.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: InventarioTrazabilidad (frontera propuesta). Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

Disponible son pares físicos en depósito. La confirmación del alta de terminados descuenta esos pares de producción en proceso. Se capturan lote y fecha de fabricación; el lote agrupa una tanda y no identifica un par. Norma informada: IRAM 3610. No hay lectores; el corte inicial usa carga manual y conserva el trabajo futuro de identificación individual/lectura. Los insumos requieren advertencias antes y después de vencer, sin bloqueo automático por vencimiento. La anticipación se configura al desarrollar ese módulo. PP/stock se actualizan entre puestos con objetivo máximo de cinco segundos.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [PARCIAL] RF-009 — Vincular lote y partida a cada par despachado y consultar su historial

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF09; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** InventarioTrazabilidad (frontera propuesta)
- **Dependencias:** INV-001
- **Alcance:** Conservar lote y fecha de fabricación en las salidas y recuperar históricamente sus clientes/pedidos. IRAM 3610 es la norma informada por el usuario; el lote identifica una tanda, no un par único.
- **Fuera de alcance:** Certificación IRAM por la sola implementación del registro.
- **Invariantes:** No confirmar salida sin lote ni fabricación válidos. No exigir partida distinta no confirmada ni confundir lote con ID individual. Conservar el vínculo histórico al movimiento y destinatario.
- **Contratos y archivos previstos:** Propuesta: registro/consulta de trazabilidad en InventarioTrazabilidad y contrato con entregas/clientes; adaptadores de persistencia y búsqueda.
- **Criterios de aceptación:** Rechazar salidas sin lote/fabricación y conservar ambos datos con cantidad, pedido y cliente destinatario. Verificar búsquedas históricas por lote/fecha, incluidas varias entregas; el máximo de dos segundos se mide en RNF-001. La verificación normativa es independiente del guardado de datos.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** implementar búsqueda histórica y validar evidencias requeridas por la edición/certificado IRAM aplicable
- **Evidencia actual:** Los movimientos firmados conservan lote, fabricación y línea de pedido, con ruta al cliente. No existe todavía buscador histórico ni se declara certificación IRAM o ID individual por par.

### [PENDIENTE] RF-010 — Calcular Stock Disponible + Producción en Proceso − Pedidos Pendientes

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF10; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** InventarioTrazabilidad (frontera propuesta)
- **Dependencias:** RF-008, RF-012
- **Alcance:** Disponible físico en depósito + pares aún en proceso − PP; el alta de terminados retira esos pares de proceso e ingresa a depósito en una transacción.
- **Fuera de alcance:** Implementar un sistema de planta completo o descontar PP del stock físico al calcular la proyección.
- **Invariantes:** Conservar signos, unidad y corte temporal de la fórmula; su resultado es una proyección y no un alta/baja física. No asumir que disponible significa stock bruto o neto de reservas.
- **Contratos y archivos previstos:** Propuesta: consulta de existencias de InventarioTrazabilidad, contrato de PP de VentasPedidos y puerto de producción en proceso cuyo origen está pendiente.
- **Criterios de aceptación:** Con datos de prueba Disponible=10, EnProceso=4 y PP=20 se obtiene -6; cada cambio de una entrada actualiza el resultado sin duplicar efectos. Se distingue una fuente no disponible de un valor cero y se documenta cómo se traduce el resultado en necesidad de inyección.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** componer la consulta y vista de necesidades con esas fuentes ya definidas, sin confundir proyección ni cuenta corriente con movimientos físicos
- **Evidencia actual:** Requisito RF10 incorporado desde la fuente conservada; sin implementación ni pruebas funcionales específicas observadas. Dependencias, contratos y casos de prueba son descomposición técnica del plan, no decisiones comerciales adicionales.

### [PENDIENTE] RF-011 — Identificar cada par y preparar picking con lectores y control de vencimientos

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF11; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** InventarioTrazabilidad (frontera propuesta)
- **Dependencias:** INV-001
- **Alcance:** Fase futura de identificación individual y lectura de códigos para picking. Actualmente solo existe un lote compartido por tanda y no hay lectores. Incorporar advertencias de insumos antes y después del vencimiento, sin bloqueo automático por vencimiento.
- **Fuera de alcance:** Comprar lectores, fijar una marca o adoptar FIFO/FEFO sin una decisión operativa.
- **Invariantes:** Un futuro ID individual no se comparte entre pares; el lote de fábrica sí puede repetirse en toda la tanda. Los vencimientos generan advertencias y conservan relación con su insumo.
- **Contratos y archivos previstos:** Propuesta: identificación/consulta de pares, selección de picking y contrato de lectura; relación con lotes de insumos/fechas y adaptador de lector por definir.
- **Criterios de aceptación:** Asignar y recuperar un ID único por par; comprobar explícitamente que dos pares no reciben el mismo ID. Probar lectura/picking y códigos repetidos/desconocidos. Configurar anticipación de alertas y mostrar avisos antes y después del vencimiento sin bloqueo automático por ese motivo.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** retomar identificación/insumos cuando se inicie su módulo; la captura manual de lotes no requiere comprar lectores para el corte actual
- **Evidencia actual:** Aclarado que no hay lectores ni códigos individuales actuales. El corte INV-001 opera manualmente por lote y fabricación; esta tarea permanece pendiente y no se atribuye su cumplimiento al alta por tanda.

### [HECHO] RF-012 — Registrar automáticamente altas de empaque y bajas por despacho o retiro

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF12; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** InventarioTrazabilidad (frontera propuesta)
- **Dependencias:** INV-001
- **Alcance:** Alta al confirmar productos terminados, descontando proceso; baja de stock y saldo de mercadería al confirmar despacho/retiro, identificando retiro de propietarios cuando corresponda.
- **Fuera de alcance:** Registrar salidas sin identidad ni autorización o inferir automatización por sensores no especificados.
- **Invariantes:** Movimiento físico y auditoría atómicos e idempotentes. El despacho requiere validación administrativa y no registra cobro ni cancela saldo monetario. No se exime de control al marcar retiro de propietarios.
- **Contratos y archivos previstos:** Propuesta: casos de uso de movimientos y contratos de ingreso/egreso con empaque y logística; persistencia y auditoría coordinadas.
- **Criterios de aceptación:** Confirmar un ingreso de empaque aumenta las existencias y confirmar un despacho/retiro las disminuye por la cantidad correspondiente; reenviar el mismo hecho no duplica el movimiento. Se prueban ausencia de autorización administrativa, errores de persistencia y coherencia entre saldo y auditoría.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** usar el flujo con cuentas y datos reales configurados; conservar las pruebas y verificar auditoría externa en SEC-001
- **Evidencia actual:** INV-001 entrega el recorrido autorizado de altas desde terminados, bajas al despachar o retirar, marca de retiro de propietarios y reducción del saldo de mercadería. Producción/stock/pedido/auditoría se confirman juntos. Verificación: 16 pruebas y 87 aserciones aprobadas, build y formato correctos, migración aplicada con copia previa. No se registran cobros ni se cancela cuenta corriente. La protección externa exigida por RNF-003 sigue explícita en SEC-001.
