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

Para decisiones posteriores usar [la plantilla](../plantillas/decision.md), conservar historial, fuente, alternativas y alcance. Las exclusiones explícitas se identifican con `DEC-AAAA-MM-DD-NN` en Fuente de las tareas afectadas.
