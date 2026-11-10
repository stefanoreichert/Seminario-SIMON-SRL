# Arquitectura de Seminario SIMON SRL

Base solicitada el 2026-11-04: arquitectura hexagonal y DDD. Perfil Laravel aplicado al stack existente. SRS-2026-11-04 aporta los requisitos de negocio y calidad. El mapa siguiente propone fronteras desde sus procesos, reglas y propiedad de datos; los contratos del corte autorizado se fijaron en INI-002 tras las aclaraciones. Los módulos pospuestos conservan sus decisiones pendientes.

## Estado observado

En la revisión inicial, la aplicación contenía el esqueleto Laravel, un modelo Eloquent `App\Models\User`, un panel Filament `admin`, la página de bienvenida y pruebas de ejemplo. `php artisan route:list --except-vendor` muestra la bienvenida como ruta propia. Después se incorporaron Acceso, ClientesAdministracion, VentasPedidos e InventarioTrazabilidad para el corte autorizado. Los demás módulos conservan su planificación; no se declara la aplicación completa. La presencia del modelo User o del panel no define por sí sola un bounded context.

## Capas y composición

| Capa | Responsabilidad | Dependencias permitidas |
| --- | --- | --- |
| Domain | Entidades, agregados, value objects e invariantes del contexto. | PHP y contratos/conceptos del dominio; independiente de Laravel, Eloquent, HTTP y proveedores. |
| Application | Casos de uso, comandos/consultas y coordinación del dominio. | Domain y puertos del núcleo; identidad, reloj, transacciones y capacidades externas mediante contratos cuando se necesiten. |
| Infrastructure | Persistencia Eloquent, transacciones, gateways y otros adaptadores. | Contratos de Domain/Application y tecnologías concretas. |
| UI/Presentation | Controladores, Blade, Livewire y recursos/acciones Filament. | Casos de uso y DTOs; valida formato y presenta resultados. Las escrituras y decisiones de negocio pasan por Application. |

Los proveedores en `app/Providers` conectan interfaces e implementaciones. Los contextos se integran mediante contratos publicados; no acceden a tablas ni adaptadores internos de otro contexto. Elegir límites transaccionales desde invariantes. Una llamada externa no comparte atomicidad con una transacción local.

## Adaptación al repositorio

La raíz del código propio es `src/`, conforme al [perfil Laravel actualizado de Promt-AI](https://github.com/wj-004/Promt-AI/blob/04f621f12421e819bfeb1cc1518c4e864617d14e/kit-inicio-proyecto/skills/arquitectura-hexagonal-ddd/references/laravel.md) y al pedido de traslado. Organizar `src/<Contexto>/{Domain,Application,Infrastructure,UI}`; Composer declara `Simon\\ => src/`. Las convenciones/autoload de Laravel se adaptan a esa raíz.

`app/Providers`, el controlador base técnico, bootstrap, config y el registro de rutas permanecen en sus ubicaciones de integración. El modelo autenticable propio vive en `src/Acceso/Infrastructure/Persistence/User.php`; los comandos de acceso/auditoría en UI/Console de sus contextos, registrados por AppServiceProvider. Las vistas propias están en UI/Views de sus contextos y se registran mediante loadViewsFrom. El tema común Filament vive en Shared/UI/Assets/filament.css; los componentes Blade compartidos en Shared/UI/Views/components. JavaScript de operaciones permanece en InventarioTrazabilidad/UI/Assets; RF-005 ubica el comportamiento de filas del pedido en VentasPedidos/UI/Assets. La entrada técnica en resources importa ambos.

DEC-2026-11-04-08 y UI-001 fijan Filament para toda la gestión. El dashboard Inicio es una página nativa en Shared/UI; las pantallas de entrada usan el layout nativo del mismo panel y adaptadores Blade de sus componentes. La composición registra tema, grupos del sidebar y middleware del panel. Las rutas propias bajo /admin mantienen autorización de servidor y Application conserva las decisiones/escrituras; los contextos no incorporan dependencia de Filament en el núcleo. Las rutas anteriores /gestion redirigen al panel conservando método y parámetros.

La migración histórica transversal conserva exactamente `2026_11_04_200000_create_operations_foundation.php` y se ubica en `src/Shared/Infrastructure/Database/Migrations`. Su nombre identifica la fila ya aplicada; no se divide ni renombra para evitar repetir DDL o perder datos. AppServiceProvider carga los directorios `src/*/Infrastructure/Database/Migrations`; las próximas migraciones pertenecen a Infrastructure de su contexto. Las tres migraciones originales del esqueleto Laravel siguen en `database/migrations` como base técnica.

No existe una excepción autorizada a src en este proyecto. DEC-2026-11-04-06 sustituye la adaptación anterior de ubicación, conservada como historia en DEC-2026-11-04-02.

Los modelos, migraciones y componentes existentes se mantienen como punto de partida. Al implementar una funcionalidad autorizada, ubicar los nuevos contratos y adaptadores y resolver sus desvíos con un alcance concreto. Para Filament, también acciones masivas, imports y relaciones que escriben deben delegar en casos de uso.

RF-007 agrega OrderStatus/OrderFingerprint en Domain y ManageOrders/OrderWorkflow en Application de VentasPedidos. El adaptador SQL conserva numeración/digests históricos, coordina cambios y despachos con el bloqueo transaccional compartido y controla la revisión de la nota. Solo Borrador admite edición; Anulado conserva entregas sin generar PP. La UI permanece en Filament. ClientesAdministracion comprueba el contrato publicado DispatchableOrders antes de autorizar; CustomerDirectory/CatalogVariants suministran opciones sin consultar adaptadores internos ajenos. DEC-2026-11-05-11 conserva las reglas delegadas de estados y duplicados activos.

RF-008 agrega PendingOrderQueries/QueryPendingOrders para los resúmenes de todas las notas pendientes y publica PendingDemand para la UI de Producción. El adaptador SQL solo consulta tablas de VentasPedidos; CustomerDirectory/CatalogVariants resuelven descripciones por contratos publicados. La producción recibe agregados por artículo sin clientes ni crédito. Shared/UI/Assets/refresh-balances.js comparte el refresco de fragmentos con plazos, avisos de error y retiro de datos ante una denegación; los formularios quedan fuera del fragmento actualizado. DEC-2026-11-05-12 conserva alcance, permisos y límites del ensayo local.

RF-009 agrega TraceabilityFilter y SearchTraceability/TraceabilityRecords en InventarioTrazabilidad; SqlTraceability consulta el contenido firmado de sus movimientos/auditorías y valida cada fila antes de publicarla. VentasPedidos publica DispatchedOrderReferences para resolver referencias antiguas sin consultar sus tablas desde inventario. MoveStock agrega a nuevas firmas copias de pedido, cliente y variante usando contratos, conservando el digest de solicitud y los reintentos históricos. Las firmas anteriores se mantienen intactas y la UI diferencia referencia actual de identidad guardada; nunca reconstruye un destino histórico ausente. DEC-2026-11-05-13 conserva políticas, permisos y límites.

## Mapa preliminar de contextos

Fuente: [SRS-2026-11-04, alcance y preguntas](../plan-maestro/02-requisitos-y-alcance.md). La agrupación funcional proviene del adjunto; su correspondencia con bounded contexts y los nombres de código son una propuesta técnica, no una decisión comercial confirmada.

| Contexto propuesto | Lenguaje, invariantes y datos propios | Requisitos |
| --- | --- | --- |
| ClientesAdministracion | Cliente, CUIT único obligatorio, historial crediticio manual, validación de facturación/crédito y condiciones. Direcciones y formularios se completan en sus tareas. Administración emite la validación requerida para una liberación; reglas y vigencia por precisar. | RF01–RF04. |
| VentasPedidos | Nota, origen S/G/F, número único, líneas por modelo/color/talle, estado y cantidades pendientes. Propietario del pedido y de su saldo comercial. | RF05–RF08. |
| InventarioTrazabilidad | Lote compartido por tanda, fecha de fabricación y movimiento físico. Identificación individual e insumos/advertencias de vencimiento quedan para RF-011. Una salida conserva trazabilidad y auditoría y requiere la validación administrativa correspondiente. | RF09–RF12; operación de RNF 01–RNF 03. |
| LogisticaPostventa | Entrega parcial, destino/zona, remito/rótulo/hoja de ruta y devolución. Coordina autorizaciones, salidas y saldos mediante contratos. | RF13–RF15. |
| Analitica | Proyecciones de ventas por curva, estados de planta, rentabilidad y estadísticas de inventario/pedidos. Consume contratos de lectura sin modificar operaciones origen. | RF16–RF17. |
| Acceso | Usuarios y roles/permisos configurables, incluidos vendedores S/G. Sin privilegios por nombre. La auditoría de movimientos se coordina en inventario con un contrato protegido. | RNF 03. |

Producción en proceso y estados del Informe de Planta requieren una fuente/contrato identificado con el área de Producción; el adjunto no define un módulo completo de planta. La propiedad del catálogo de artículos/variantes, costos y demás datos maestros sigue pendiente, sin introducir otro contexto por inferencia.

## Contratos de integración propuestos

| Emisor / propietario | Consumidor | Datos o capacidad publicados |
| --- | --- | --- |
| ClientesAdministracion | VentasPedidos / LogisticaPostventa | Identidad del cliente, direcciones y datos de destino; definir vigencia y conservación histórica. |
| ClientesAdministracion | LogisticaPostventa / InventarioTrazabilidad | Autorización con evidencia de facturación y estado crediticio; alcance, revocación y comprobación por precisar. |
| VentasPedidos | LogisticaPostventa / InventarioTrazabilidad | Identidad/estado de nota y líneas por variante; saldo y cantidades admitidas por entrega. |
| LogisticaPostventa | VentasPedidos / InventarioTrazabilidad | Identidad de entrega, pares/cantidades efectivas y autorización; aplicación de saldo y movimiento sin duplicación. |
| LogisticaPostventa | VentasPedidos / InventarioTrazabilidad | Devolución y referencia a su operación: nota negativa y reintegro de existencias según reglas a definir. |
| InventarioTrazabilidad / VentasPedidos / fuente de Producción | Proyección de necesidades / Analitica | Disponible, PP y producción en proceso con unidad/corte temporal; trazabilidad, movimientos e indicadores con procedencia. |

Las formas concretas de DTOs, errores, transacciones, idempotencia y comunicación se deciden por recorrido. Definir cómo se conserva la consistencia entre autorización, stock y saldo ante fallo/reintento/concurrencia antes de implementar RF12/RF14/RF15. La fórmula de RF10 es una consulta de planificación y no modifica stock físico. No acceder a tablas de otro contexto ni adoptar un bus o consistencia eventual sin justificarlo.

INI-002 cierra los contratos del corte autorizado. Las preguntas de formularios/reportes/exportación pospuestos no son prerrequisitos de ese corte. La estructura implementada está bajo `src/<Contexto>/{Domain,Application,Infrastructure,UI}` con namespace raíz `Simon\\`; Shared se limita a identidad, contratos de autorización/transacción y excepciones comunes.

## Contratos y persistencia del corte

- AccessPolicy resuelve identidad y permiso en servidor; ManageAccess configura usuarios/roles. SqlAccess implementa el adaptador y protege el último administrador activo.
- RF-006 define OrderOrigin en Domain de VentasPedidos y ListOrders/OrderQueries en Application. La consulta autoriza al actor, conserva el alcance por vendedor o permiso global y aplica el filtro S/G/F en persistencia antes de limitar resultados. UI consume el caso de uso de lectura en lugar de decidir el alcance mediante un adaptador SQL; las etiquetas no conceden acceso.
- RF-005 define OrderLines en Domain para validar de una a cien líneas distintas y cantidades enteras positivas, sin dependencias del framework. RegisterOrder conserva la autorización y delega el guardado conjunto al puerto existente. InventarioTrazabilidad publica CatalogVariants y su adaptador verifica la existencia de variantes; ventas consume el contrato y no sus tablas. La infraestructura confirma cabecera y líneas en una transacción o revierte todo. La dirección se comprueba después de resolver un reintento, para conservar su copia histórica aun si el domicilio actual se inactivó. OrderRequest, OrderFormData y las vistas/JavaScript de VentasPedidos traducen entradas y recuperan el formulario dentro de Filament.
- MoveStock coordina InventoryStore, UnitOfWork, OrderFulfillment y DispatchAuthorization. Los adaptadores de ventas y administración implementan los puertos de inventario; el núcleo no importa esos adaptadores.
- SqlInventory posee existencias, tandas en proceso como entrada operativa, movimientos y auditoría. Los saldos de pedido se modifican mediante el puerto publicado por ventas; las validaciones se obtienen por el puerto de administración.
- RF-002 publica DeliveryAddresses desde ClientesAdministracion: listado por cliente y resolución de un domicilio activo para su copia. Ventas e inventario consumen el contrato y no leen las tablas de domicilios. ManageAddresses valida permisos y DeliveryAddress valida sus datos sin depender de Laravel.
- El pedido conserva su destino en JSON; el despacho conserva la copia realmente utilizada dentro de su payload firmado. La selección se verifica en la transacción y el reintento se resuelve antes de leer la dirección actual. La referencia lógica delivery_address_id no sustituye la copia histórica ni introduce un acceso a la persistencia de otro contexto. Pedidos antiguos mantienen destino null hasta resolver una entrega; las firmas antiguas no se reescriben.
- Un único motor transaccional coordina estas escrituras en el monolito. Primero se reserva el escritor de inventario; se revalida saldo/stock mediante actualizaciones condicionadas y se bloquea la autorización en motores que admiten FOR UPDATE. Un fallo revierte pedido, producción, stock, movimiento y firma.
- Reintentos de la misma operación/actor/datos devuelven el resultado previo; cambiar los datos con el mismo token se rechaza. La numeración global de notas y los identificadores S/G/F no se confunden con los tokens técnicos.
- La confirmación de terminados descuenta pares en proceso e incrementa depósito. El despacho disminuye depósito y pares pendientes; ninguna de esas operaciones liquida cuenta corriente monetaria.
- Auditoría: fecha/hora UTC, actor, cantidad, lote/fabricación y copia de autorización administrativa en payload firmado; historial crediticio manual conserva versiones con actor/fecha. Actualizaciones y borrados de movimientos/auditoría se rechazan por SQL.
- La retención es indefinida. La firma y su cadena no equivalen a resistencia al control total del servidor: SEC-001 agrega cierres/copias externas inmutables y credenciales separadas. Esta limitación queda en RNF-003.

No se crearon modelos Eloquent de negocio; la infraestructura usa Query Builder y el modelo User trasladado a Infrastructure/Persistence de Acceso. Auth, factory explícita, seeder, pruebas y Filament utilizan ese modelo; La migración aditiva soporta SQLite/MySQL para las protecciones declaradas; las pruebas locales de este corte verifican SQLite, no operación real de MySQL ni concurrencia de volumen de producción.

No se adoptan microservicios, CQRS, event sourcing, outbox o un bus por defecto; registrar su necesidad si un recorrido lo justifica.

DEC-2026-11-10-01 y DAT-002 establecen MySQL/base simonsrl para el sistema. La conexión local mysql utiliza el servidor MariaDB 10.4.32 de XAMPP; la configuración y .env.example se alinean con esa elección. La transferencia desde SQLite conserva cuenta, permisos, metadatos y respaldo. Los nuevos movimientos almacenan y firman fecha UTC en formato SQL con precisión de segundos para ajustarse al timestamp existente, sin modificar firmas anteriores. Las pruebas SQLite históricas conservan su alcance; la verificación MySQL local se registra por separado en DAT-002 y no acredita concurrencia ni rendimiento productivos.

## Verificación

Al recibir requisitos, probar invariantes, casos de uso y contratos; agregar pruebas de adaptadores, permisos, concurrencia y UI según los criterios afectados. SQLite local no acredita concurrencia sobre otro motor. Las pruebas actuales comprueban el esqueleto, no reglas de negocio.

Procedimiento: [skill de arquitectura](../../.agents/skills/arquitectura-hexagonal-ddd/SKILL.md) y [perfil Laravel](../../.agents/skills/arquitectura-hexagonal-ddd/references/laravel.md). Fuentes y decisiones: [registro](../decisiones/README.md).

## Verificación del traslado

REF-001 valida autoload optimizado con PSR-4 estricto, modelo/factory de autenticación, descubrimiento de comandos y migraciones desde src, preservación del identificador de la migración instalada, rutas, auditoría y regresión completa de operaciones. Composer conserva las versiones de dependencias. El kit y las skills se actualizan a la referencia 04f621f12421e819bfeb1cc1518c4e864617d14e, reteniendo las correcciones Windows.
