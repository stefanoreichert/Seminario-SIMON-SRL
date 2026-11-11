# Decisiones de Seminario SIMON SRL

## DEC-2026-11-04-01 — Integrar arquitectura hexagonal y DDD

- Fecha y estado: 2026-11-04, adoptada para la preparación y trabajo posterior autorizado.
- Fuente: solicitud del usuario de implementar el prompt maestro y kit según wj-004/Promt-AI.
- Decisión: mantener tres skills locales, un único esquema versión 1 y un plan en Markdown compartido por validador, reportes y visor.
- Alternativas: mantener solo el esqueleto actual; generar herramientas diferentes. Se reutiliza el kit solicitado para conservar compatibilidad.
- Alcance: herramientas y documentación; las funcionalidades se desarrollan con requisitos y pedidos concretos.

## DEC-2026-11-04-02 — Adaptar al stack y estructura existentes

Ubicación histórica bajo app sustituida por DEC-2026-11-04-06. Se conservan las decisiones de stack y la evidencia previa.

- Fecha y estado: 2026-11-04, adoptada como adaptación de la instalación.
- Fuente: `composer show --direct`, `php --version`, `package.json`, rutas, configuración y convenciones actuales; reglas de `AGENTS.md`.
- Decisión: perfil Laravel, PHP 8.4, Laravel 13, Filament 5 y Livewire 4 observados. Futuras capas por contexto bajo `app` y autoload `App\`.
- Alternativas: perfil neutral u otra raíz de código. Se conserva el stack existente y se consulta cualquier cambio material futuro.
- Alcance: conciliar `AGENTS.md` agregando enlaces y principios, sin reemplazar las reglas Laravel; kit reutilizable en `resources/kit-inicio-proyecto`.
- Consecuencias: no se cambian dependencias, rutas de producto, autoload ni datos. El despliegue y el motor de producción quedan por definir.

## DEC-2026-11-04-03 — Mantener pendientes las definiciones de negocio

- Fecha y estado: 2026-11-04, decisión expresa del usuario; la postergación general queda actualizada por DEC-2026-11-04-04 al recibir los requisitos.
- Fuente: respuesta «si, dejalo pendiente» a la consulta sobre problema, actores y procesos.
- Decisión: registrar preguntas abiertas y próximos pasos en la ficha y el plan. No inventar requisitos, módulos, fechas ni criterios comerciales.
- Alternativas: relevar ahora esos requisitos. Se difiere esa definición y se completa la preparación independiente.
- Alcance: INI-001, INI-002 e INI-003 conservan el trabajo que falta. No constituye exclusión de funcionalidad ni impedimento para usar el kit.

## DEC-2026-11-04-04 — Incorporar los requisitos suministrados al plan maestro

- Fecha y estado: 2026-11-04, incorporación solicitada por el usuario.
- Fuente: adjunto conservado como [SRS-2026-11-04](../plan-maestro/fuentes/2026-11-04-requisitos-originales.txt) y pedido «los requerimentos del sistema son los siguientes, agregalos al plan maestro».
- Decisión: registrar íntegramente RF01–RF17 y RNF 01–RNF 03, conservar sus IDs originales en Fuente y crear tareas compatibles RF-001–RF-017 y RNF-001–RNF-003. Actualizar ficha, preparación, mapa preliminar y visor del mismo plan.
- Alternativas: conservar la postergación general o incorporar solo una síntesis. Se mantiene el texto original y una correspondencia completa para evitar pérdidas.
- Alcance: documentación y planificación. SOLICITADO indica que el requisito fue aportado para el sistema; este pedido no inicia su implementación. Las veinte tareas permanecen PENDIENTE.
- Consecuencias: DEC-2026-11-04-03 conserva su historia, pero ya hay requisitos conocidos. Los contratos, dependencias técnicas y criterios detallados son propuestas de descomposición; las preguntas DEF-01–DEF-14 registran reglas todavía no definidas. El mapa de contextos no se considera aprobado por el solo hecho de documentarlo.
- Límites: la mención de formularios Rentas, normas IRAM o seis meses legales en la fuente no acredita vigencia normativa ni certificación. Su comprobación pertinente se incluye en las tareas antes del desarrollo.

## DEC-2026-11-04-05 — Precisiones y autorización del primer flujo

- Fuente: [SRS-ACL-2026-11-04](../plan-maestro/03-decisiones-relevamiento.md), respuestas del usuario a los puntos agrupados.
- Estado y fecha: 2026-11-04, decisiones de negocio informadas y decisiones técnicas delegadas.
- Decisión: usuarios/roles/permisos configurables; Sandra/Gustavo usuarios S/G; CUIT único obligatorio; crédito y condiciones manuales de administración; número global y estados aceptados; stock físico; alta de terminados descuenta proceso; lote/fabricación e IRAM 3610; avisos de vencimiento sin bloqueo automático.
- Autorización: implementar alta de terminados y baja al confirmar despacho, con reducción del saldo de pares y deuda monetaria separada. ACC-001 cubre permisos necesarios e INV-001 el primer recorrido. No se interpreta como autorización de todos los módulos del plan.
- Postergaciones: hojas de ruta a módulo específico, reportes para después, exportaciones/importaciones cuando estén disponibles y canal Internet futuro. Las preguntas pospuestas no bloquean el corte autorizado.
- Devoluciones: seis meses desde fabricación como regla operativa aportada, aprobación por Claudia según permisos, reingreso solo si apto y nota negativa sin reemplazo. La conformidad legal se valida al trabajar esa función.
- Seguridad: retención indefinida. Se eligen permisos mínimos, transacción de movimiento/auditoría, append-only y firmas encadenadas con clave fuera de la base. SEC-001 agrega evidencia externa resistente al control total del servidor; RNF-003 no se cierra hasta verificarla.
- Alternativas: mantener un rol fijo por nombre, mezclar deuda con cantidades o bloquear el avance por reportes/exportaciones pospuestos. Se adoptan contratos y módulos separados.
- Definiciones abiertas acotadas: duplicado comercial y edición/anulación tras parciales, ejemplares impositivos, anticipación de alertas, datos/volúmenes reales y detalles de módulos futuros. Se conservan sin otra ronda de preguntas.

## DEC-2026-11-04-06 — Trasladar el código propio a src

- Fecha y estado: 2026-11-04, solicitada por el usuario e implementada en REF-001.
- Fuente: pedido expreso de consultar Promt-AI actualizado y trasladar contextos, Composer, namespaces, migraciones, skills y documentación. Referencia fijada al commit 04f621f12421e819bfeb1cc1518c4e864617d14e.
- Decisión: raíz src y prefijo propio Simon; contextos Acceso, ClientesAdministracion, InventarioTrazabilidad, VentasPedidos y soporte Shared. La composición técnica conserva App y las rutas exigidas por Laravel.
- Modelo y comandos: User se ubica en Acceso/Infrastructure/Persistence y sus factories/auth se conectan explícitamente. Los comandos de acceso/auditoría se registran desde UI/Console de sus contextos.
- UI y activos: las vistas propias usan UI/Views y namespaces de vistas registrados en la composición. CSS/JavaScript de operaciones se ubican en UI/Assets; resources conserva los puntos técnicos de entrada Vite y la bienvenida del esqueleto.
- Migraciones: mover la migración transversal histórica a Shared/Infrastructure/Database/Migrations conservando su nombre/identidad; registrar descubrimiento desde src. Las migraciones técnicas iniciales de Laravel no se reescriben.
- Alternativas: conservar contextos bajo App por su autoload heredado o duplicarlos con aliases. Se adopta el traslado solicitado y se actualizan todas las referencias activas, sin copiar clases duplicadas.
- Alcance y consecuencias: refactorización de ubicación/autoload; sin cambiar reglas de negocio, versiones de dependencias, identificadores ni datos. La evidencia de decisiones previas se conserva marcada como histórica.
- Recursos: actualizar kit 1.1.0 y skills locales, registrar source_root=src y source_root_override=false. Las fuentes adicionales/exportaciones ya pospuestas no generan otra ronda de preguntas ni cambian esta autorización.

## DEC-2026-11-04-07 — Destinos por cliente y conservación histórica en RF-002

- Fecha y estado: 2026-11-04, implementación autorizada expresamente por el usuario para RF-002, una tarea por vez.
- Fuente: RF02 de SRS-2026-11-04 y pedido posterior «ok, implementa» tras concretar el alcance de múltiples domicilios y selección en pedidos/entregas.
- Decisión técnica: ClientesAdministracion administra direcciones con etiqueta, domicilio, localidad y provincia obligatorios; código postal, país y referencias opcionales. clients.manage permite alta/consulta/edición y desactivación sin borrado. La desactivación retira el domicilio de nuevas selecciones; no modifica ni revoca las copias ya confirmadas.
- Integración: DeliveryAddresses es el contrato publicado de lectura/resolución. Ventas e inventario lo consumen sin consultar tablas internas de Clientes. Al seleccionar se comprueba pertenencia al cliente y estado activo en servidor; vendedores y depósito consultan destinos con sus permisos existentes sin acceso al crédito.
- Historia: el pedido guarda copia de su destino al confirmar. Cada despacho conserva la copia utilizada en el contenido firmado; puede usar la copia del pedido o elegir explícitamente otro domicilio activo del mismo cliente. Editar un domicilio no cambia pedidos ni movimientos anteriores.
- Compatibilidad: pedidos anteriores o nuevos con destino por definir conservan null. Un despacho normal exige resolver el destino antes de confirmar; los retiros de propietarios identificados pueden carecer de destino externo, manteniendo autorización y auditoría. No se inventan domicilios ni se reescriben firmas previas.
- Reintentos: identificar la selección por ID dentro del digest y resolver el resultado anterior antes de volver a leer un domicilio editable. Cuando no se envía una dirección, se conserva el formato previo de los digests para recuperar operaciones históricas.
- Alternativas: enlazar pedidos/despachos al domicilio actual o actualizar retrospectivamente sus destinos cambiaría el historial. Se eligen copias por operación y cambios explícitos para entregas nuevas.
- Límites: no agrega mapas, rutas, remitos, facturación ni un módulo logístico completo; esos requisitos mantienen sus tareas. Las pruebas locales usan SQLite y no acreditan otro motor ni carga productiva.

## DEC-2026-11-04-08 — Filament como interfaz común y continuidad de las RF

- Fecha y fuente: 2026-11-04, el usuario pidió el mismo estilo Filament para todo, con panel izquierdo organizado, y seguir implementando RF desde esa base.
- Decisión: interfaz común dentro del panel admin, sidebar nativo con grupos Comercial, Fábrica y Administración, componentes de la versión instalada y tema compartido compatible con claro/oscuro y tamaños adaptables. AGENTS.md conserva esta regla para implementaciones posteriores.
- Organización: clientes y pedidos en Comercial; catálogo, producción, stock y despachos en Fábrica; autorizaciones, usuarios, roles y auditoría en Administración. La visibilidad y el acceso directo dependen de permisos existentes, sin privilegios por persona.
- Integración: las vistas/controladores propios siguen en src/UI y usan layout/componentes de Filament; las escrituras siguen delegando en Application. Dashboard Inicio conecta módulos, sin datos comerciales inventados ni widgets promocionales del framework.
- Compatibilidad: rutas canónicas /admin y redirecciones desde /gestion; POST/PUT preservan método/cuerpo mediante 307 y vuelven a pasar por autorización/validación. No se modifica el esquema de negocio ni sus datos.
- Alcance: UI-001 reorganiza y unifica capacidades existentes; no acredita entrega de RF futuras. La solicitud autoriza continuar las RF según el plan, una tarea por vez, manteniendo evidencia y límites expresamente pospuestos.
- Alternativa: conservar un dashboard Filament con pantallas externas de otro aspecto. Se adopta el panel y su tema como marco común para la gestión.
- Verificación: suite y build del producto, más revisión visual. UI-001 conserva PARCIAL mientras la comprobación visual completa no pueda realizarse; no se confunde HTML aprobado con aceptación humana ni ergonomía industrial.

## DEC-2026-11-04-09 — Consulta por origen sin ampliar permisos en RF-006

- Fecha y fuente: 2026-11-04, el usuario autorizó completar RF-006 tras concretar etiquetas S · Sandra, G · Gustavo, F · Fábrica, filtro por origen y controles de identificador dentro de Filament.
- Decisión: OrderOrigin es la definición de los tres códigos y sus denominaciones. El código del pedido representa su origen comercial; no se deriva del nombre actual de una persona ni concede permisos.
- Registro: se mantiene la política existente: orders.create exige el identificador de la cuenta autenticada; orders.manage permite elegir cualquier origen al registrar. Un reintento con otro origen no modifica una nota confirmada. La edición de pedidos y sus transiciones conservan su tarea RF-007.
- Consulta: ListOrders aplica autorización y alcance por actor antes de usar el puerto OrderQueries. El filtro refina ese alcance; no lo amplía ni permite elegir un vendedor mediante parámetros. Los permisos de lectura global existentes se conservan.
- UI: filtro GET y tabla por notas dentro del panel Filament. Las etiquetas también aparecen en el registro, saldos y autorizaciones. El fragmento de refresco conserva el filtro aplicado; no se reinicia al recibir datos actualizados.
- Límite existente: hasta 200 notas recientes por consulta permitida. Se filtra en persistencia antes del límite y se informa en la pantalla; no se filtran únicamente las últimas 200 notas de todos los orígenes.
- Alternativa: filtrar en el navegador o inferir privilegios por letra/nombre. Se eligen consultas autorizadas en servidor y reglas de dominio, evitando exposición de notas ajenas.
- Alcance: sin migraciones, reclasificación de notas previas, creación de cuentas personales ni cambios en otras RF. Las pruebas locales y el renderizado no sustituyen aceptación humana o carga productiva; UI-001 mantiene su revisión visual pendiente.

## DEC-2026-11-04-10 — Carga conjunta y dependencia de seguridad de RF-005

- Fecha y fuente: 2026-11-04, el usuario autorizó completar RF-005 después de concretar validaciones de múltiples artículos, conservación de cliente/destino/origen y revisión de la dependencia de seguridad.
- Dependencia corregida: RF-005 dependía de RF-001, RF-002 y RNF-003. El requisito RNF 03 de la fuente exige auditoría inmutable de altas/bajas de stock; registrar una nota no modifica existencias ni registra un movimiento físico. La dependencia necesaria para el acceso de pedidos es ACC-001, además de clientes/direcciones. Se sustituye esa arista por ACC-001; RNF-003 y SEC-001 conservan sus criterios, estado pendiente/parcial y alcance externo.
- Verificación del límite: probar que confirmar una nota, con la auditoría externa aún pendiente, solo escribe su cabecera y líneas; no modifica stock, tandas ni movimientos/auditoría de inventario. Los despachos siguen el circuito autorizado y auditado existente.
- Líneas: conservar los límites existentes del formulario de una a cien líneas y de uno a un millón de pares por línea, también en llamadas al caso de uso. Un artículo repetido se rechaza y se conserva el formulario para corregirlo; no se combinan cantidades silenciosamente.
- Consistencia: todas las combinaciones y cantidades se confirman en una transacción. Artículo inválido, dirección ajena/inactiva o fallo de una línea revierten la nota completa. Reintentos conservan la identificación y semántica vigentes. La dirección actual se comprueba después de resolver el token dentro de la transacción: editar/inactivar un domicilio no invalida el reenvío exacto ni modifica su copia histórica, y una nota nueva sigue rechazando ese domicilio inactivo.
- UI: formulario de VentasPedidos dentro del mismo panel Filament, con alta/baja de filas, errores por línea y recuperación de valores tras un rechazo. Cambiar cliente vuelve a consultar sus destinos; un error no reemplaza un destino válido ni el origen elegido por otro automáticamente.
- Alternativas: esperar la infraestructura externa para probar una operación sin movimientos de stock, guardar ítems por separado o descartar filas inválidas. Se elige la dependencia de acceso pertinente y una confirmación conjunta.
- Alcance: únicamente RF-005; sin nuevos estados de pedido, facturación, importaciones, cambios de permisos por persona ni modificación de los requisitos de auditoría.

## DEC-2026-11-05-11 — Estados y duplicados activos en RF-007

- Fecha y fuente: 2026-11-05. El usuario autorizó RF-007 («ok sigue con ello»), delegó parciales («Define tu, la opcion que mejor se adapte») y exigió prevenir duplicados delegando la regla («el sistema no debe permitir eso. Decide tu»). Resuelve estas incógnitas de DEC-2026-11-04-05.
- Estados: Borrador admite editar cabecera, destino y artículos; no genera PP, autorizaciones ni despachos. Confirmar conserva el número y resuelve una dirección activa del cliente. Confirmado y Parcial admiten entregas; Entregado y Anulado son terminales. Las correcciones de notas confirmadas requieren anular el saldo y registrar otra.
- Anulación: Borrador/Confirmado/Parcial permiten cerrar lo no entregado con motivo obligatorio de hasta 1000 caracteres. Se conservan cantidades originales, entregas, autorizaciones, stock descontado y auditoría firmada; PP pasa a cero. No genera devolución, reingreso ni movimiento monetario.
- Duplicación comercial: se rechazan dos notas activas (Borrador/Confirmado/Parcial) del mismo cliente con idénticas variantes/cantidades, independientemente de orden de filas, origen, vendedor o destino. No se fusionan cantidades. Entregado/Anulado permiten una compra nueva con token nuevo; el token original recupera siempre la nota original. La regla también impide compras simultáneas idénticas: se resuelven sobre la nota activa antes de registrar otra. No se inventa una ventana temporal.
- Compatibilidad: conservar digests históricos, numeración NP global basada en la identidad y números anulados. No renumerar, borrar ni fusionar posibles duplicados históricos; la comprobación considera activos anteriores.
- Permisos y concurrencia: orders.create gestiona notas propias; orders.manage, cualquier origen. Depósito/consulta mantiene lectura sin edición. La versión rechaza pantallas desactualizadas; registro, cambios y despachos comparten el bloqueo transaccional local. La historia comercial registra actor, fecha UTC, acción, estados y motivo; no sustituye SEC-001.
- Contratos: VentasPedidos publica OrderWorkflow/DispatchableOrders; ClientesAdministracion comprueba el estado mediante ese contrato al autorizar. CustomerDirectory/CatalogVariants proveen opciones a UI; el núcleo no incorpora Laravel ni adaptadores ajenos.
- Alcance: RF-007 e integraciones necesarias; migración aditiva de versión, índice y tabla de cambios. RF-008, revisión visual general UI-001, motor productivo, devoluciones y evidencia externa mantienen sus tareas.

## DEC-2026-11-05-12 — Consulta completa de pendientes y demanda para Producción

- Fecha y fuente: 2026-11-05, el usuario autorizó RF-008 con «ok implementa» después de concretar resúmenes por cliente/modelo/color/talle, vista de Producción, permisos y medición entre puestos.
- Lectura: los totales incluyen todos los pedidos Confirmado/Parcial con cantidad restante positiva; no se calculan desde las últimas 200 notas. Se agrupa por cliente/variante y por variante, con páginas de 25 grupos y totales completos independientes de la página. Borradores, anulados y entregados no generan PP. La consulta no modifica pedidos, stock, crédito ni auditoría.
- Autorización: orders.create consulta solo notas propias, aun filtrando otro origen o enviando seller_id ajeno. Los lectores globales existentes conservan su alcance. production.manage/inventory.view consultan la demanda global por artículo en Producción mediante PendingDemand; ese contrato no publica clientes, destinos, crédito ni líneas de notas. inventory.receive por sí solo no concede esa consulta.
- Integración: QueryPendingOrders coordina PendingOrderQueries y contratos publicados CustomerDirectory/CatalogVariants; SqlPendingOrders consulta únicamente tablas propias de VentasPedidos y toma una instantánea transaccional de totales/páginas. Las descripciones se resuelven por ID sin el límite de 500 opciones del formulario. El núcleo no depende de Laravel ni de infraestructura ajena.
- Interfaz: menú Comercial/Pendientes y resumen de artículos dentro de Fábrica/Producción, con shell/componentes Filament. El detalle anterior de notas conserva su límite de 200 y enlaza la consulta completa. La demanda muestra pares por entregar para planificar; Stock Disponible + En Proceso − PP pertenece a RF-010 y no se presenta como implementado.
- Refresco: un módulo compartido consulta cada dos segundos sin superponer solicitudes, con plazo de 2500 ms; conserva filtros/páginas, formulario abierto y foco de enlaces. Un fallo mantiene el último resultado con aviso de desactualización. Una denegación/redirección de autenticación retira el contenido; volver a una pestaña dispara una consulta inmediata. Una falla de red no se presenta como garantía de actualización en cinco segundos.
- Ensayo: cinco contextos activos de Edge 156.0.4314.58, cinco trabajadores HTTP, SQLite temporal, 1000 notas y 5000 líneas sintéticas iniciales; 30 observaciones de seis acciones, máximo 2279,90 ms hasta el DOM tras dos cuadros de renderizado. Windows 10.0.26200 x64, Ryzen AI 9 365, 20 CPU lógicas, 23 GiB RAM reportada; PHP 8.4.23 y Node 24.18.0. Las primeras ejecuciones con un único servidor PHP de prueba se saturaron y no acreditan ese resultado. La ejecución válida usó varios trabajadores y cerró sus procesos/perfil/base temporal.
- Límite de cierre histórico: RF-008 quedó PARCIAL porque la revisión automática impidió ejecutar la suite completa por cuota de uso agotada, sin emitir un juicio de inseguridad. El 2026-11-05, tras «ok hazlo», se pudo completar Pint --dirty --test y la suite de 104 pruebas/1304 aserciones; RF-008 se cerró antes de iniciar RF-009. Se conservan los límites sobre red industrial, volumen real, MySQL productivo y revisión general UI-001.

## DEC-2026-11-05-13 — Buscador histórico y copias de identidad en las salidas

- Fecha y fuente: 2026-11-05, el usuario indicó «ok hazlo» después de concretar cierre de RF-008 y RF-009 como búsqueda histórica de lotes/fabricación con cliente, pedido, destino y cantidad. RF-008 se cerró con 104 pruebas/1304 aserciones y Pint antes de iniciar RF-009.
- Consulta: buscar lote exacto y/o intervalo inclusivo de fabricación; sin filtros no se recorre el historial. Se consultan despachos y retiros con paginación de 25 movimientos, orden descendente e inclusión de entregas parciales/notas posteriormente anuladas. No se usa el límite de 100 auditorías recientes ni el de 200 pedidos. Los signos %/_ y comillas son contenido literal, no operadores de búsqueda.
- Historia: lote, fabricación, actor, cantidad y destino provienen del payload firmado de cada movimiento. No se reconstruye el destino desde una dirección editable ni desde la copia actual del pedido. Las nuevas salidas agregan una copia firmada de número/origen de pedido, cliente con CUIT y descripción de variante; posteriores cambios de maestros no la reemplazan.
- Compatibilidad: no hay migración ni reescritura de firmas anteriores. Si un movimiento antiguo no conserva esas identidades, se resuelven sus referencias mediante DispatchedOrderReferences y CustomerDirectory/CatalogVariants y se marca «Actual» en la fila. Un destino histórico ausente sigue ausente. Los reintentos se resuelven antes de volver a leer identidades/direcciones y no agregan copias nuevas a movimientos anteriores.
- Integridad: antes de mostrar cada fila se comprueban HMAC, enlace anterior disponible y correspondencia de los campos de movimiento. JSON inválido, firma alterada o enlace roto producen error explícito, sin presentarlos como cero coincidencias. La consulta no equivale a verificar externamente toda la cadena ni cubre truncamientos/restauraciones del servidor; SEC-001/RNF-003 mantienen su alcance.
- Permisos: se utilizan inventory.view, inventory.dispatch, audit.view, orders.manage o dispatch.authorize para la consulta global. orders.create, production.manage, inventory.receive o clients.manage solos no habilitan este historial. Sidebar, URL directa y Application comprueban la autorización; no se publican crédito, condiciones administrativas ni payloads completos.
- Arquitectura: TraceabilityFilter en Domain y SearchTraceability/TraceabilityRecords en Application de InventarioTrazabilidad. SqlTraceability consulta únicamente movimientos/auditorías propios. Ventas publica DispatchedOrderReferences; la composición conecta el adaptador de ventas. MoveStock obtiene metadatos por contratos antes de firmar, dentro de la transacción existente. UI permanece en el panel Filament, módulo Fábrica/Trazabilidad.
- Alcance normativo: las aclaraciones previas fijan lote compartido y fabricación, sin inventar una partida distinta ni ID por par. El [servicio oficial IRAM de EPP](https://www.iram.org.ar/servicio/elementos-de-proteccion-personal/) describe auditorías de fábrica y ensayos de producto; el [Plan de estudio IRAM 2026, página 79](https://www.iram.org.ar/UserFiles/pdf/Plan_de_Estudio_2026.pdf) incluye IRAM 3610 en estudio de modificación. Esas fuentes no identifican la edición/certificado aplicable a SIMON. Su confirmación documental queda pendiente; esta entrega acredita la funcionalidad digital, sin declarar certificación ni conformidad normativa del producto.
- Límites: RF-009 no cierra RNF-001 (máximo de dos segundos), identificación individual RF-011, MySQL productivo, infraestructura externa ni revisión visual general UI-001. La prueba de navegador de RF-009 es funcional/visual y no se presenta como ensayo de rendimiento de RNF-001.

## DEC-2026-11-10-01 — MySQL y base simonsrl

- Estado: confirmada.
- Fecha y fuente: 2026-11-10, el usuario indicó «utiliza para el sistema mysql, la base de datos simonsrl».
- Decisión: usar la conexión mysql de Laravel y simonsrl como nombre de base; la elección del motor deja de estar pendiente. La configuración local, sus valores por defecto y .env.example reflejan esa elección.
- Entorno observado: XAMPP ofrece MariaDB 10.4.32 en 127.0.0.1:3306, accesible mediante el conector mysql. Esto no acredita una instalación Oracle MySQL ni define el servidor de producción.
- Conservación: simonsrl existía sin tablas. Se aplicaron las ocho migraciones existentes y se transfirieron los datos persistentes de SQLite en una transacción, comparando el contenido de las 23 tablas incluidas. Se preservaron usuario, hash de contraseña, rol, doce permisos, asignación y metadatos de migraciones/auditoría. Caché y sesiones son transitorios y no se copiaron; SQLite original y respaldo consistente permanecen disponibles.
- Compatibilidad: la sesión SQL utiliza UTC (+00:00) y los nuevos movimientos guardan y firman la misma fecha en formato SQL con precisión de segundos, conforme a la columna timestamp existente. Se conserva el contenido firmado anterior; no se reescriben firmas históricas. Las pruebas MySQL detectaron el rechazo del formato ISO con microsegundos, la necesidad de comprobar JSON inválido antes de filtrar el historial y la reposición del índice de cliente al revertir add_order_workflow; esos casos se corrigieron y verificaron en una base temporal.
- Alcance: DAT-002 cubre configuración, transferencia local y verificación de compatibilidad. La carga comercial externa, concurrencia de producción, rendimiento industrial y despliegue conservan sus tareas propias.
- Alternativa: continuar con SQLite contradiría la elección expresa. Las pruebas automáticas habituales conservan SQLite temporal; la compatibilidad MySQL se comprueba en una base separada de simonsrl.
- Evidencia y próximo paso: DAT-002 cerrado con 117 pruebas/1447 aserciones en SQLite, 53 pruebas/748 aserciones sobre mysql/MariaDB, reversión/reaplicación de migraciones en base temporal y auditoría real con secuencia 0/digest inicial. Respaldo storage/app/backups/before-mysql-20261110.sqlite con integrity_check aprobado y manifiesto storage/app/backups/mysql-transfer-20261110.json. Base temporal eliminada; configuración, pruebas y límites conservados en DAT-002.

## DEC-2026-11-10-02 — Proyección por artículo y necesidad de inyección en RF-010

- Fecha y fuente: 2026-11-10, el usuario indicó «ok implementa» después de identificar RF-010 como la siguiente funcionalidad, con DAT-002, RF-008 y RF-012 cerrados. Esta decisión técnica concreta el alcance autorizado y no inicia otra RF.
- Cálculo: disponible es la suma física registrada en depósito, antes de restar PP. Se suman los pares todavía en proceso y se restan las cantidades aún no despachadas de Confirmado/Parcial. Borrador, Anulado y Entregado no generan PP. ProductionProjection conserva el signo; la necesidad de inyección en pares es max(0, -saldo). No se compensan talles/artículos distintos, descuentan reservas adicionales ni registran movimientos al consultar. Un alta desde proceso conserva el saldo proyectado; un despacho reduce tanto stock como PP, sin doble descuento.
- Lectura y frontera: SqlProductionPlanning consulta exclusivamente catálogo, stock y producción del contexto. PendingDemand publica las cantidades globales para los artículos solicitados, resueltas en VentasPedidos. La lectura transaccional adquiere un bloqueo compartido del coordinador inventory_audit_head que usan las escrituras existentes, sin incrementar lock_revision; las tres entradas de la proyección se componen dentro de esa lectura. Se pagina todo el catálogo a 25 artículos, sin reutilizar los límites de opciones/lotes o notas recientes. No se agregan tablas, migraciones ni dependencias.
- Permisos y fallos: production.manage o inventory.view habilitan la proyección en UI y Application; inventory.receive aislado conserva su pantalla previa sin esta consulta. Producción recibe artículos y cantidades, sin clientes, crédito ni condiciones administrativas. Los adaptadores traducen fallos de lectura a ReadSourceUnavailable; un ID omitido por el contrato también invalida la proyección. Una fuente no disponible muestra un aviso y no se sustituye por cero. Cero es válido cuando la consulta de esa fuente terminó correctamente.
- Interfaz: Fábrica/Producción conserva Filament, el resumen de demanda de RF-008 y el refresco existente. RF-010 añade tabla de escritorio, tarjetas móviles, paginación propia que conserva la de demanda y señales textuales de saldo/faltante. El refresco no reemplaza formularios y retira los datos al denegar acceso. La consulta muestra necesidad en pares; no deduce tandas, materiales, costos ni decisiones de planta adicionales.
- Evidencia y límites: ProductionProjectionTest y ProductionNeedsTest cubren fórmula, ceros, negativos inválidos, agregaciones completas, estados, cambios, idempotencia, permisos, fallos y ausencia de escrituras. La regresión y la prueba MySQL/MariaDB aislada se registran en RF-010. El navegador verifica actualización, foco/formulario, temas y móvil con datos temporales. Esto no acredita rendimiento industrial, concurrencia de producción ni la aceptación visual general de UI-001.

## DEC-2026-11-11-01 — Identificación y picking por par, con activación controlada

- Fuente: solicitud posterior a RF-010 de completar las tareas pendientes, recibida el 2026-11-10; implementación/verificación el 2026-11-11. Se mantiene una RF por vez y no se considera HECHO un despliegue pendiente.
- Modelo: PairUnit conserva identidad/ciclo Disponible → Seleccionado → Despachado y admite liberar una selección sin cambiar su código. Se activa explícitamente la identificación de los pares físicos de un lote; códigos aleatorios PAR de 96 bits e índice único impiden compartir identidad. Lotes aún no activados mantienen su flujo previo y no se reescriben salidas históricas. Activado el lote, nuevos ingresos generan las identidades adicionales y sus salidas requieren los códigos seleccionados. No se decide FIFO/FEFO.
- Consistencia: identificación, lectura, liberación y despacho usan el mismo coordinador que las escrituras de stock/pedidos. El núcleo verifica variante, línea, lote y saldo; la lista de códigos integra el payload firmado del despacho. Los eventos de picking son inmutables y la identidad/lote del par no puede cambiarse por SQL ordinario. No se atribuye protección ante un administrador capaz de eliminar tablas/disparadores sin SEC-001.
- Vencimientos: SupplyExpiry clasifica fechas, con anticipación configurada por producción. No se inventa una anticipación inicial; los vencidos y el vencimiento de hoy se avisan siempre. Nombre/lote conservan la relación con el insumo y puede indicarse una tanda propia. Vencimiento genera advertencia, sin bloqueo automático de recepción/producción.
- UI y lectura: módulos nativos Filament, roles existentes y campo con foco para lectores que emiten texto/Enter. Las pruebas cubren entrada equivalente por navegador; no había hardware real. RNF-002 conserva su validación industrial. No se compra dispositivo ni se declara compatibilidad con un SDK de marca.
- Aplicación pendiente: las pruebas con SQLite y MySQL/MariaDB en bases aisladas aprobaron. La revisión automática rechazó ejecutar respaldo y migración sobre simonsrl porque exige autorización explícita para modificar la base compartida. Se solicitó esa autorización; la migración aditiva de cinco tablas no se aplicó. PickingReady y el adaptador preservan la operación anterior durante esta espera. RF-011 queda PARCIAL hasta aplicar/verificar.

## DEC-2026-11-11-02 — Pesos argentinos como moneda inicial

- Estado: confirmada por el usuario, al responder la consulta para completar cuenta corriente e informes: «la moneda que se utilizara sera pesos argentinos. e igualmente para mas adelante se podra agregar otra moneda».
- Usar ARS inicialmente y conservar la moneda de cada saldo/movimiento para una ampliación futura. No introducir conversiones, cotizaciones o sumas entre monedas por inferencia. Los saldos iniciales y las definiciones de rentabilidad/rotación/obsolescencia conservan sus preguntas pendientes.

## DEC-2026-11-11-03 — Cuenta corriente manual y separación monetaria

- Fuente y alcance: solicitud de completar pendientes y confirmación ARS de DEC-2026-11-11-02. FIN-001 se implementa dentro de ClientesAdministracion, que conserva el cliente y su deuda manual; no se crea un contexto por una pantalla ni se mezcla el saldo monetario con pares físicos.
- Money conserva unidades menores enteras, código de moneda y precisión; inicialmente la aplicación habilita únicamente ARS con dos decimales. El parser admite coma o punto decimal sin separador de miles, evita punto flotante y rechaza exceso de precisión. No hay cotizaciones, conversiones ni totales que mezclen monedas; una moneda futura requiere habilitar su configuración sin cambiar asientos históricos.
- La apertura requiere un importe y una fuente explícitos, incluso si el importe es cero. Una cuenta sin apertura no acredita deuda cero. Cargos suman y cobros restan importes positivos; ajustes admiten signo y exigen motivo; un saldo negativo representa crédito del cliente. Conciliar compara el saldo de la fuente y agrega evidencia sin sumar de nuevo su importe. No se inventan saldos iniciales ni se declaran conciliadas fuentes no aportadas.
- CurrentAccount conserva identidad cliente/moneda y sus reglas; ManageCurrentAccounts comprueba permisos y coordina el repositorio propio. El adaptador bloquea por cuenta y registra actor, operación única, fuente, fecha UTC e importe/saldo posterior. Apertura y asiento son atómicos; historia y moneda/precisión de la cuenta son inmutables por SQL ordinario. Un ajuste conserva lo anterior y un reintento no agrega otro movimiento. Esta protección no sustituye la infraestructura externa de SEC-001.
- UI Filament en Administración/Cuenta corriente. finance.view/finance.manage son permisos separados, configurables mediante Roles; no se otorgan automáticamente a cuentas reales ni a quien solo tenga clientes o inventario. El inventario no cancela deuda al entregar y los cobros no modifican cantidades, estados de pedidos ni auditoría física.
- Verificación: pruebas de dominio, casos, persistencia, permisos, reversión y concurrencia; compatibilidad MySQL y navegador con datos aislados. La migración aditiva de dos tablas se conserva sin aplicar a simonsrl, junto con la de RF-011, pendiente de autorización explícita para esa base compartida. La importación real conserva DAT-001 y los reportes mantienen sus fórmulas pendientes; FIN-001 queda PARCIAL hasta activar el esquema y registrar las fuentes reales que correspondan.

## DEC-2026-11-11-04 — Consulta de autorizaciones separada por pedido

- Fuente y alcance: solicitud del usuario del 2026-11-11 para simplificar Autorizaciones y separar la gestión por pedido. No modifica las condiciones de despacho ni completa las reglas pendientes de vigencia y revocación.
- Lectura: QueryDispatchAuthorizations exige dispatch.authorize y compone información mediante OrderQueries de VentasPedidos, CustomerDirectory/CustomerRecords de ClientesAdministracion y CatalogVariants de InventarioTrazabilidad. La búsqueda usa número o identidades de clientes coincidentes; el paginado incluye todos los pedidos confirmados o parciales. Cada adaptador consulta tablas de su contexto, sin acoplar UI a tablas ajenas. Las lecturas son sucesivas y no representan una instantánea transaccional entre contextos.
- Interfaz y escritura: una lista muestra las validaciones de cada pedido y el detalle recupera cantidades y condiciones existentes. Livewire conserva el permiso en cada solicitud; ManageCustomers mantiene la validación y escritura existentes. Sin migraciones, cambios de dependencias ni modificación automática de datos reales.
- Verificación y límites: pruebas de acceso, búsqueda, aislamiento, estados y guardado; navegador con datos temporales. La identidad del validador se muestra por su identificador; no se incorpora un nuevo directorio de usuarios. La revisión no acredita concurrencia ni vigencia definitiva.

Para decisiones posteriores usar [la plantilla](../plantillas/decision.md), conservar historial, fuente, alternativas y alcance. Las exclusiones explícitas se identifican con `DEC-AAAA-MM-DD-NN` en Fuente de las tareas afectadas.
