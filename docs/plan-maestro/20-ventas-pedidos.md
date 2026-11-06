# Ventas y pedidos

## Propósito y fuentes

La nota de pedido agrupa artículos por modelo, color, talle y cantidad; tiene origen, número único y estado. Este contexto conserva las cantidades solicitadas y los saldos pendientes, integrando confirmaciones de entregas mediante contratos explícitos.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: VentasPedidos (frontera propuesta). Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

Sandra y Gustavo son usuarios vendedores con identificadores S y G; F permanece como origen Fábrica. Numeración global y estados Borrador/Confirmado/Parcial/Entregado/Anulado aceptados. DEC-2026-11-05-11 resuelve RF-007 por delegación: edición solo en Borrador, anulación del saldo restante conservando entregas y rechazo de notas activas idénticas por cliente/variantes/cantidades. Los reintentos con la misma identificación no crean otro pedido. El catálogo existe; permisos de mantenimiento se configuran por rol. PP debe reflejar cambios entre puestos en un máximo de cinco segundos.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [HECHO] RF-005 — Cargar múltiples artículos en un solo formulario de nota de pedido

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF05; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05; autorización explícita del 2026-11-04 de completar únicamente RF-005 y revisar su dependencia de seguridad; DEC-2026-11-04-10
- **Contexto propietario:** VentasPedidos (frontera propuesta)
- **Dependencias:** RF-001, RF-002, ACC-001
- **Alcance:** Capturar y guardar en una nota varias líneas con modelo, color, talle y cantidad, sin guardar cada ítem individualmente.
- **Fuera de alcance:** Importar planillas o archivos masivos: la fuente solicita un formulario con múltiples artículos.
- **Invariantes:** La nota conserva cada combinación y su cantidad. Entre una y cien líneas distintas, con cantidades enteras de uno a un millón de pares. Artículos repetidos o inválidos rechazan la nota completa sin sumar ni descartar líneas silenciosamente. El token y la copia del destino conservan la semántica de reintento existente.
- **Contratos y archivos previstos:** src/VentasPedidos/Domain/OrderLines.php; RegisterOrder/OrderRegistration y SqlOrderRegistration; CatalogVariants publicado por InventarioTrazabilidad e implementado por SqlInventory; UI/OrderRequest.php, OrderFormData.php, Views/order-form.blade.php, Views/order-line.blade.php y Assets/order-form.js en VentasPedidos. Composición AppServiceProvider, integración OperationsController y tests/Feature/MultipleArticleOrdersTest.php.
- **Criterios de aceptación:** Un formulario permite ingresar varios artículos y guardarlos conjuntamente en una nota; al consultar se mantienen todas las combinaciones y cantidades. Se verifica una carga con una línea inválida y se aplica el comportamiento de guardado acordado sin pérdida silenciosa de ítems.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** ninguno en RF-005; tarea cerrada. Continuar una sola tarea del plan por vez cuando el usuario lo indique. UI-001 conserva su revisión visual pendiente.
- **Evidencia actual:** Base histórica de INV-001: formulario inicial con varias líneas y confirmación conjunta; se habían anotado direcciones y otros recorridos incompletos. RF-002 cerró las direcciones posteriormente. Cierre de RF-005 del 2026-11-04 bajo DEC-2026-11-04-10:
  - Comercial / Pedidos, en /admin/pedidos, conserva layout, sidebar y controles Filament. Permite agregar y quitar filas, mantiene al menos una y aplica el límite de cien; cada artículo se elige por código, modelo, color y talle junto con su cantidad.
  - El formulario comienza vacío para evitar seleccionar automáticamente un cliente/artículo/origen administrativo. Tras un rechazo recupera todas las filas, cantidades, cliente, dirección, origen y token válidos, con errores junto a cada campo. Cambiar cliente recarga sus destinos; la recuperación no borra la dirección elegida. Un pedido confirmado redirige a la consulta del mismo origen.
  - OrderLines protege las invariantes también fuera de HTTP: rechaza tipos incorrectos, cantidades inválidas, filas incompletas o repetidas y columnas ajenas al contrato. CatalogVariants verifica existencia mediante un contrato publicado; la persistencia vuelve a comprobarla antes de insertar y confirma cabecera/líneas dentro de la transacción existente.
  - Pruebas verifican que una segunda línea inválida no guarde parte del pedido y que un fallo SQL simulado en una línea posterior revierta cabecera y líneas anteriores. Corregir el error permite confirmar todas las combinaciones/cantidades; reenviar el mismo token no duplica la nota. No se agregan escrituras de stock, tandas ni auditoría de movimientos.
  - Dos subagentes revisaron arquitectura e interfaz en modo lectura. Detectaron una comprobación HTTP de dirección activa que impedía reintentos históricos: se retiró y la vigencia permanece en la transacción después de resolver el token. Una prueba HTTP confirma conservación de ID/número/destino tras editar e inactivar la dirección y rechazo de esa dirección para una nota nueva. También se ajusta el número de los avisos al quitar filas.
  - Nueva cobertura: catorce pruebas en MultipleArticleOrdersTest sobre carga conjunta, recuperación, permisos, duplicados, tipos/columnas, catálogo, límites, reversión, reintentos y ausencia de movimientos físicos. El conjunto RF-005/direcciones/orígenes aprobó 41 pruebas y 559 aserciones. Suite final: php vendor/phpunit/phpunit/phpunit, 77 pruebas y 1040 aserciones aprobadas. Pint, npm.cmd run build, node --check de ambos scripts y git diff --check aprobados.
  - RF-001, RF-002 y ACC-001 están HECHO y sus recorridos permanecen cubiertos por la suite. Sin migraciones ni cambios de dependencias, credenciales o datos comerciales reales; php artisan migrate --pretend devuelve Nothing to migrate. No se importaron catálogos reales. Evidencia funcional/renderizado HTTP sobre SQLite y revisión estática de JavaScript; no acredita clics en navegador, revisión visual, MySQL productivo ni operación con volumen real. UI-001, RNF-003, SEC-001 y RF-007 mantienen su alcance y estado propios.

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

### [HECHO] RF-007 — Asignar numeración única y controlar estados para prevenir pedidos duplicados

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF07; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05; autorización del usuario del 2026-11-05 «ok sigue con ello»; reglas delegadas sobre parciales/duplicados y DEC-2026-11-05-11
- **Contexto propietario:** VentasPedidos (frontera propuesta)
- **Dependencias:** ACC-001, RF-005, RF-006
- **Alcance:** Numeración única global con origen separado S/G/F y estados aceptados Borrador, Confirmado, Parcial, Entregado y Anulado; prevenir reintentos duplicados y precisar duplicación comercial.
- **Fuera de alcance:** Asignar una secuencia, período o política de cancelación por inferencia.
- **Invariantes:** Número global único y conservado. Mismo token/payload recupera la nota sin reabrirla. No hay dos notas activas idénticas por cliente/variantes/cantidades. Borrador no admite despachos ni genera PP; Anulado no genera PP y conserva entregas. Solo Borrador admite edición; confirmar/anular exige versión y permisos. Cambios e historia se confirman o revierten juntos.
- **Contratos y archivos previstos:** OrderStatus/OrderFingerprint; ManageOrders, OrderWorkflow, DispatchableOrders y registro/consultas; SqlOrderWorkflow, SqlOrderRegistration, SqlOrderFulfillment y migración aditiva. OrderController, OrderRequest, OrderFormData y vistas Filament; composición, contratos de lectura publicados y OrderWorkflowTest/OrderConcurrencyTest.
- **Criterios de aceptación:** Altas concurrentes producen números únicos; reenviar la misma operación no genera duplicados según la clave de operación acordada; las notas muestran su estado y rechazan transiciones no permitidas. La detección de duplicados comerciales utiliza una regla expresamente definida.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-05
- **Próximo paso:** ninguno en RF-007; cerrada el 2026-11-05. La siguiente tarea funcional es RF-008, sin iniciarla en esta entrega. UI-001 mantiene su revisión visual general; el motor productivo y volumen real conservan sus verificaciones pendientes.
- **Evidencia actual:** Base histórica: NP-000001 global, reintentos y estados Confirmado/Parcial/Entregado. Cierre del 2026-11-05 bajo DEC-2026-11-05-11:
  - Filament permite guardar borradores completos, abrir el detalle de cada nota, editar cabecera/dirección/artículos de Borrador, confirmar los datos guardados y anular solo el saldo con motivo. La navegación conserva el módulo Pedidos seleccionado. Las notas muestran estado, cantidades originales/entregadas/pendientes e historia de cambios con actor/fecha UTC.
  - Confirmar un borrador vuelve a resolver el destino activo; después conserva su copia incluso si se modifica/inactiva la dirección. Reenviar el token recupera el resultado anterior antes de releer datos mutables. Solo Confirmado/Parcial permiten validación administrativa y despachos; Borrador/Anulado tienen PP cero. Anular tras 4 de 10 conserva 4 entregados, stock descontado, condiciones, crédito y firmas; los 6 restantes dejan de generar saldo.
  - Se rechazan notas activas del mismo cliente con idénticas variantes/cantidades aunque cambien orden de filas, origen o destino. Registro, edición y confirmación comprueban la regla bajo el mismo bloqueo que los despachos. Las notas entregadas/anuladas permiten compras nuevas; el token histórico no se convierte en otra nota. Posibles duplicados históricos no se borran ni fusionan.
  - orders.create solo modifica notas propias; orders.manage opera cualquier origen; lectores consultan sin escribir. La revisión de la nota cambia con edición/transición/despacho y rechaza formularios viejos. Estado, líneas e historia se revierten juntos ante fallos; una nota entregada/anulada no se reabre. Domain/Application no importan Laravel, Filament ni adaptadores ajenos; los cruces adicionales usan contratos publicados.
  - Diecinueve pruebas nuevas: quince de flujo/permisos/HTTP/direcciones/errores/idempotencia/reversión y cuatro con dos procesos PHP simultáneos sobre SQLite temporal. Verifican reintentos concurrentes con una sola nota, rechazo de cargas idénticas con tokens diferentes, números únicos para pedidos distintos y exclusión entre despacho/anulación desactualizada. Fixtures de RF-006 usan cantidades distintas para notas independientes, conservando sus aserciones de orígenes y aislamiento.
  - Suite final: php vendor/phpunit/phpunit/phpunit --no-progress, 96 pruebas y 1193 aserciones aprobadas. Pint --dirty --test, npm.cmd run build, node --check de ambos scripts y git diff --check aprobados. Las cuatro rutas admin/pedidos de listado/detalle/edición/transición están registradas y protegidas. El chequeo de imports es estructural; la evidencia funcional proviene de las pruebas.
  - Copia SQLite consistente e integrity_check previos en storage/app/private/rf007-before-20261106T001914Z.sqlite (marca UTC; fecha local 2026-11-05). Migración 2026_11_05_210000_add_order_workflow aplicada en lote 5; migrate:status la confirma. Se compararon las 24 tablas anteriores usando sus columnas originales: datos conservados, excepto el incremento técnico de lock_revision que realiza inventory:audit-verify al tomar el bloqueo. integrity_check y foreign_key_check aprobados. No se cargaron clientes, cuentas, catálogo, pedidos ni movimientos de prueba en la base real. El verificador conserva secuencia 0 y digest sin cambios.
  - La concurrencia ensayada es SQLite local, no MySQL productivo. El renderizado HTTP y el build no acreditan revisión visual en navegador, ergonomía industrial ni volumen real; UI-001/RNF conservan esos límites. ACC-001, RF-005 y RF-006 están HECHO y permanecen cubiertos por la regresión. El plan/visor comparten el esquema 1; su validador documental no sustituye estas pruebas.

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
