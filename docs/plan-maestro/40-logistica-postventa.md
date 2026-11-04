# Logística y postventa

## Propósito y fuentes

Preparar entregas y documentos, confirmar despachos parciales y registrar devoluciones. El saldo del pedido pertenece a VentasPedidos y el movimiento físico a InventarioTrazabilidad; logística coordina los contratos sin modificar tablas internas de esos contextos.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: LogisticaPostventa (frontera propuesta). Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

El usuario autorizó implementar baja física y reducción de pares pendientes al confirmar despacho, sin cancelar cuenta corriente monetaria. Se identifica retiro de propietarios sin deducir exención de registro/autorización. Remitos y hojas de ruta actuales son manuales; las hojas se difieren a LOG-001. Devoluciones: seis meses desde fabricación como regla operativa informada, aprobación de Claudia según permiso, reintegro a stock vendible solo si el par es apto y nota negativa sin reposición pendiente.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [PENDIENTE] RF-013 — Emitir e imprimir remitos, rótulos y hojas de ruta por zona

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF13; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** LogisticaPostventa (frontera propuesta)
- **Dependencias:** RF-002, RF-014, LOG-001
- **Alcance:** Generación automática e impresión de remitos de entrega, rótulos de identificación y hojas de ruta agrupadas por zona geográfica.
- **Fuera de alcance:** Optimización de recorridos, GPS o integración con transportistas no solicitados expresamente.
- **Invariantes:** Los documentos corresponden a la entrega, cliente y destino correctos y sus cantidades coinciden con el despacho; definir numeración, regeneración y reimpresión.
- **Contratos y archivos previstos:** Propuesta: contratos de documentos de entrega y agrupación por zona, adaptador de generación/impresión y consultas de destinos/entregas.
- **Criterios de aceptación:** Una entrega genera los tres tipos de documentos con datos y cantidades correctos; varias entregas producen hojas de ruta para las zonas acordadas. Se prueba impresión/reimpresión con las plantillas y dispositivos definidos, sin generar nuevos movimientos de stock.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** retomar documentos cuando se solicite ese módulo; remitos actuales son manuales y las hojas de ruta quedan para el módulo específico LOG-001
- **Evidencia actual:** Requisito RF13 incorporado desde la fuente conservada; sin implementación ni pruebas funcionales específicas observadas. Dependencias, contratos y casos de prueba son descomposición técnica del plan, no decisiones comerciales adicionales.

### [PARCIAL] RF-014 — Confirmar despachos parciales y reducir el saldo hasta completar el pedido

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF14; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** LogisticaPostventa (frontera propuesta)
- **Dependencias:** INV-001
- **Alcance:** Entregar parte de una nota, registrar cantidades efectivas y actualizar automáticamente su saldo adeudado hasta la finalización.
- **Fuera de alcance:** Cerrar la nota completa por una entrega parcial o descontar mercadería aún no despachada.
- **Invariantes:** Una misma entrega no se descuenta dos veces; no descontar cantidades superiores al saldo según la regla acordada. Validación administrativa, trazabilidad y movimiento físico deben corresponder a la misma operación.
- **Contratos y archivos previstos:** Propuesta: caso de uso de confirmación de entrega y contratos de autorización, trazabilidad, salida física y aplicación de cantidades entregadas al pedido.
- **Criterios de aceptación:** En una nota de 10 pares, entregar 4 deja 6 pendientes y entregar los 6 restantes cierra el saldo; se prueban parciales por artículo, reintentos y dos confirmaciones concurrentes. Un fallo no deja un saldo o stock definitivo incoherente; la estrategia de coordinación se define antes de implementar.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** completar recorridos logísticos, destinos y documentación sobre el núcleo de parciales autorizado
- **Evidencia actual:** El corte actualiza pedido Confirmado → Parcial → Entregado al despachar cantidades y protege saldo/stock con transacción, CAS e idempotencia. Las pruebas 10→4→6 verifican saldos sin cancelar cuenta corriente; los recorridos logísticos completos siguen pendientes.

### [PENDIENTE] RF-015 — Registrar devoluciones, notas de pedido negativas y reintegro de stock

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF15; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** LogisticaPostventa (frontera propuesta)
- **Dependencias:** RF-014, RNF-003
- **Alcance:** Devoluciones aprobadas por Claudia mediante permiso configurable; plazo operativo de seis meses desde fabricación del calzado. Solo pares aptos se reintegran al stock vendible; nota negativa registra devolución sin generar reemplazo pendiente.
- **Fuera de alcance:** Emitir una conclusión legal, equiparar nota de pedido negativa con nota de crédito fiscal o inventar una política de rechazo.
- **Invariantes:** No reponer pares no aptos al stock vendible ni generar reposiciones pendientes desde la nota negativa. Mantener lote/fabricación, aprobación y operación única; validar aplicabilidad legal por separado.
- **Contratos y archivos previstos:** Propuesta: casos de uso de devolución y contratos de nota negativa en VentasPedidos y reintegro/auditoría en InventarioTrazabilidad; relación con entrega y par por acordar.
- **Criterios de aceptación:** Aplicar la regla operativa desde fabricación con casos dentro/en/fuera del límite, aprobación autorizada y evaluación de aptitud. Una devolución apta genera nota negativa y reintegro una sola vez; una no apta se registra sin sumar stock vendible. Ninguna genera reemplazo pendiente automáticamente.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** implementar el módulo cuando se solicite; conservar estas definiciones sin repetir la consulta de negocio
- **Evidencia actual:** Requisito RF15 incorporado desde la fuente conservada; sin implementación ni pruebas funcionales específicas observadas. Dependencias, contratos y casos de prueba son descomposición técnica del plan, no decisiones comerciales adicionales.

### [PENDIENTE] LOG-001 — Desarrollar el módulo específico de hojas de ruta

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** LogisticaPostventa
- **Dependencias:** RF-014
- **Alcance:** Digitalizar las hojas de ruta actualmente manuales en una fase posterior; definir zonas y formatos allí.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Agrupar las entregas correctas por las zonas definidas, conservar cantidades y destinos y generar documentos reproducibles; optimización de recorridos solo si se solicita.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** pospuesto por el usuario; retomar al iniciar el módulo logístico específico
- **Evidencia actual:** Trabajo registrado a partir de SRS-ACL-2026-11-04; sin implementación de este módulo todavía.
