# Ventas y pedidos

## Propósito y fuentes

La nota de pedido agrupa artículos por modelo, color, talle y cantidad; tiene origen, número único y estado. Este contexto conserva las cantidades solicitadas y los saldos pendientes, integrando confirmaciones de entregas mediante contratos explícitos.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: VentasPedidos (frontera propuesta). Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

Sandra y Gustavo son usuarios vendedores con identificadores S y G; F permanece como origen Fábrica. Numeración global y estados Borrador/Confirmado/Parcial/Entregado/Anulado aceptados. Pendientes para RF-007: edición/anulación tras parciales y regla de duplicado comercial. Los reintentos con la misma identificación no crean otro pedido. El catálogo existe; permisos de mantenimiento se configuran por rol. PP debe reflejar cambios entre puestos en un máximo de cinco segundos.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [PARCIAL] RF-005 — Cargar múltiples artículos en un solo formulario de nota de pedido

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF05; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** VentasPedidos (frontera propuesta)
- **Dependencias:** RF-001, RF-002, RNF-003
- **Alcance:** Capturar y guardar en una nota varias líneas con modelo, color, talle y cantidad, sin guardar cada ítem individualmente.
- **Fuera de alcance:** Importar planillas o archivos masivos: la fuente solicita un formulario con múltiples artículos.
- **Invariantes:** La nota conserva la correspondencia entre cada combinación y su cantidad; definir validaciones y tratamiento de líneas repetidas antes del desarrollo.
- **Contratos y archivos previstos:** Propuesta: nota y líneas de pedido, contrato de identificación de artículos/variantes, caso de uso de carga conjunta y formulario de ventas.
- **Criterios de aceptación:** Un formulario permite ingresar varios artículos y guardarlos conjuntamente en una nota; al consultar se mantienen todas las combinaciones y cantidades. Se verifica una carga con una línea inválida y se aplica el comportamiento de guardado acordado sin pérdida silenciosa de ítems.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** integrar dirección de entrega y completar los criterios comerciales del formulario
- **Evidencia actual:** El corte registra varias líneas de modelo/color/talle/cantidad en un formulario y confirma una nota; restan las direcciones completas de RF-002 y demás recorridos comerciales. No se importaron catálogos reales.

### [HECHO] RF-006 — Identificar el origen de las notas mediante S, G y F

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF06; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05; autorización explícita del 2026-11-04 de completar RF-006 con etiquetas y filtro S/G/F dentro de Filament; DEC-2026-11-04-09
- **Contexto propietario:** VentasPedidos (frontera propuesta)
- **Dependencias:** ACC-001
- **Alcance:** Clasificar y consultar notas según S para Sandra, G para Gustavo y F para Fábrica.
- **Fuera de alcance:** Conceder permisos por el nombre de una persona o permitir que un vendedor suplante otro identificador.
- **Invariantes:** Conservar exactamente las tres correspondencias de la fuente; el origen no concede roles ni identifica por sí solo al usuario autenticado.
- **Contratos y archivos previstos:** src/VentasPedidos/Domain/OrderOrigin.php; casos RegisterOrder/ListOrders y puerto OrderQueries en Application; SqlOrderRegistration y binding en AppServiceProvider. Integración de OperationsController, vistas Filament index/balances y tests/Feature/OrderOriginsTest.php.
- **Criterios de aceptación:** Se registra y recupera cada origen con su letra y denominación correctas; se distingue entre notas S, G y F al consultar. Se rechazan valores no definidos y se acuerda quién puede modificar el origen.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** ninguno en RF-006; tarea cerrada. Las cuentas e identificadores reales se configuran desde ACC-001 en la puesta en marcha, sin inventar usuarios. Continuar una sola tarea pendiente del plan por vez.
- **Evidencia actual:** Base de INV-001: identificadores únicos configurables, servidor que exige el identificador del vendedor y permiso orders.manage para registrar cualquier origen. Cierre de RF-006 del 2026-11-04:
  - OrderOrigin centraliza S · Sandra, G · Gustavo y F · Fábrica como conjunto cerrado, sin dependencias del framework. Registro, opciones y etiquetas de consulta usan esa misma definición y rechazan valores no definidos en HTTP y casos de uso.
  - Pedidos en /admin/pedidos incorpora filtro por origen y opción Todos los orígenes, tabla de notas con letra/nombre, estado y destino, y origen visible en líneas pendientes y autorizaciones. Selectores, secciones, botones y badges conservan Filament y su sidebar. Quitar el filtro recupera la consulta permitida al usuario.
  - ListOrders coordina el puerto OrderQueries y comprueba permisos en Application. Un vendedor consulta solo sus propias notas, aun filtrando otro origen o enviando seller_id ajeno; los permisos existentes de gestión/depósito/administración conservan su consulta global. El filtro no concede permisos de registro.
  - Cada vendedor registra únicamente su identificador; orders.manage permite elegir cualquiera al registrar. El origen confirmado se conserva: reenviar un token con otro origen se rechaza en lugar de modificar o duplicar la nota. No se agrega una edición del origen ni las transiciones futuras de RF-007.
  - El refresco automático conserva el origen aplicado en la URL del fragmento y mantiene aisladas sus líneas. El filtro se ejecuta en SQL antes del límite existente de 200 notas recientes, explicitado en la pantalla; se verifica con una nota de otro origen anterior a 201 notas nuevas. No se modifica la paginación/consulta histórica de otros requisitos.
  - Pruebas específicas: 11 pruebas y 187 aserciones aprobadas. Incluyen registro/recuperación S/G/F, etiquetas, filtrado, identificadores suplantados, permisos de lectores, nombres que no conceden acceso, filtros inválidos/arreglos, preservación del filtro, aislamiento entre notas del mismo origen y reintento sin reclasificación. Suite completa: 63 pruebas y 857 aserciones aprobadas; Pint, npm.cmd run build y git diff --check aprobados.
  - Sin cambios de dependencias, esquema, credenciales, asignaciones personales ni datos comerciales reales. migrate --pretend devuelve Nothing to migrate. La evidencia es funcional/renderizado HTTP sobre SQLite local; no acredita MySQL productivo ni revisión visual adicional. UI-001 conserva por separado su verificación visual pendiente.

### [PARCIAL] RF-007 — Asignar numeración única y controlar estados para prevenir pedidos duplicados

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF07; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** VentasPedidos (frontera propuesta)
- **Dependencias:** ACC-001, RF-005, RF-006
- **Alcance:** Numeración única global con origen separado S/G/F y estados aceptados Borrador, Confirmado, Parcial, Entregado y Anulado; prevenir reintentos duplicados y precisar duplicación comercial.
- **Fuera de alcance:** Asignar una secuencia, período o política de cancelación por inferencia.
- **Invariantes:** Dos notas no comparten el identificador acordado; los reintentos y operaciones concurrentes no deben crear la misma nota dos veces. Qué constituye un pedido duplicado debe definirse más allá del número.
- **Contratos y archivos previstos:** Propuesta: puerto de numeración/registro de notas, transiciones de estado y restricciones de persistencia; pruebas de concurrencia y reintentos.
- **Criterios de aceptación:** Altas concurrentes producen números únicos; reenviar la misma operación no genera duplicados según la clave de operación acordada; las notas muestran su estado y rechazan transiciones no permitidas. La detección de duplicados comerciales utiliza una regla expresamente definida.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** completar Borrador/Anulado y reglas de edición/anulación posterior a parciales; mantener pendiente la duplicación por contenido sin bloquear nuevas operaciones legítimas
- **Evidencia actual:** Se genera NP-000001 y sucesivos desde una secuencia global. El token de registro reutilizado devuelve el mismo pedido; no se deduplican pedidos distintos por compartir cliente/artículos. El corte crea Confirmado y avanza a Parcial/Entregado; faltan los recorridos indicados.

### [PARCIAL] RF-008 — Consultar pedidos pendientes y mercadería adeudada con actualización en tiempo real

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF08; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** VentasPedidos (frontera propuesta)
- **Dependencias:** INV-001, RF-007
- **Alcance:** Consulta de cantidades adeudadas por cliente/artículo y necesidades de producción derivadas de los pedidos pendientes.
- **Fuera de alcance:** Planificación automática completa de planta o promesas de producción no especificadas.
- **Invariantes:** El saldo refleja cantidades pedidas y entregas confirmadas sin descontar dos veces una entrega parcial; estados y ajustes que afectan PP deben acordarse.
- **Contratos y archivos previstos:** Propuesta: contrato publicado de consulta de PP en VentasPedidos y recepción de entregas de LogisticaPostventa; proyección de lectura para producción.
- **Criterios de aceptación:** Las altas/entregas actualizan los pares pendientes sin doble descuento y los cambios aparecen en otros puestos en un máximo de cinco segundos en el escenario acordado. Verificar también la visualización de necesidades de producción y su relación con RF-010, no solo el saldo de pedidos.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** completar la vista de necesidades de producción y medir latencia con el volumen real disponible
- **Evidencia actual:** Existe consulta de líneas pedidas/despachadas/pendientes con refresco de saldos cada dos segundos sin recargar formularios. Las pruebas comprueban los saldos y el fragmento HTML; el máximo de cinco segundos y su comportamiento con volumen real aún requieren ensayo operativo.
