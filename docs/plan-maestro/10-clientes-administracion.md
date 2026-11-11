# Gestión de clientes y administración

## Propósito y fuentes

Gestionar datos comerciales, direcciones y antecedentes crediticios; administración valida facturación y crédito antes de liberar mercadería. La documentación impositiva para entidades públicas pertenece a esta área funcional; su frontera definitiva se revisa en INI-002.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: ClientesAdministracion (frontera propuesta). Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

Confirmados: CUIT único obligatorio; historial crediticio y condiciones de despacho cargados/verificados manualmente por Nadia según permisos configurables. Los SR 322/344 corresponden a documentación publicada por ATM Misiones; el usuario indicó ARCA/Misiones y esa referencia se conserva en la fuente de aclaraciones. Los ejemplares de formularios se revisarán al trabajar RF-004. El saldo de pares y la deuda monetaria son independientes.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md). La solicitud posterior de completar únicamente RF-001 amplía el perfil comercial y su consulta/edición; los demás requisitos conservan su estado.

## Tareas

### [HECHO] RF-001 — Registrar, consultar y actualizar perfiles comerciales e historial crediticio

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF01; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05; solicitud explícita del 2026-11-04 de completar únicamente RF-001 y notificar su cierre
- **Contexto propietario:** ClientesAdministracion (frontera propuesta)
- **Dependencias:** ACC-001
- **Alcance:** Datos comerciales del cliente (CUIT, teléfono, correo) e historial crediticio consultable y actualizable según permisos.
- **Fuera de alcance:** Aprobar crédito automáticamente o integrar un sistema externo de facturación no especificado.
- **Invariantes:** CUIT único y obligatorio; historial y condiciones verificados/cargados por quien tenga el permiso de administración correspondiente. No deducir solvencia automáticamente ni borrar la deuda al despachar.
- **Contratos y archivos previstos:** src/ClientesAdministracion: CustomerProfile en Domain; ManageCustomers/CustomerRecords en Application; SqlCustomers y migración 2026_11_04_210000_add_customer_contact_fields en Infrastructure; CustomerController y vistas en UI. Integración de rutas, proveedores, navegación compartida y enlace desde operaciones; tests/Feature/CustomerProfilesTest.php.
- **Criterios de aceptación:** Registrar/consultar/actualizar CUIT, teléfono, correo e historial crediticio; rechazar CUIT vacío o repetido y accesos no autorizados. Verificar explícitamente actualización y recuperación del historial. La evaluación y carga del crédito son manuales.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** ninguno en RF-001; tarea cerrada. Los requisitos siguientes se abordan por separado con su propia autorización.
- **Evidencia actual:** Base previa de INV-001: CUIT normalizado único/obligatorio, alta e historial manual. Ampliación RF-001 del 2026-11-04:
  - Seis rutas de clientes: listado/búsqueda paginada, alta, ficha editable, actualización de datos comerciales y nueva observación crediticia. Acceso desde Clientes en el panel y en la navegación de gestión; operaciones enlaza el mismo módulo.
  - Nombre/CUIT obligatorios; teléfono/correo opcionales para conservar perfiles históricos sin inventar contactos. Se consultan/editan los cuatro campos. La regla de dominio valida también llamadas directas; CUIT se normaliza y su índice único rechaza duplicados al registrar/editar, permitiendo conservar el propio CUIT.
  - Perfil e historial se escriben por separado: editar contactos no reemplaza observaciones. Cada actualización manual conserva las anteriores y se recupera con autor/fecha UTC, sin mezclar clientes. Si falla el registro histórico, se revierte la modificación del estado actual o el alta completa.
  - clients.manage se verifica en rutas y casos de uso; usuarios sin permiso o desactivados no acceden. La búsqueda alcanza todos los clientes por nombre/CUIT/teléfono/correo, con 25 resultados por página y caracteres comodín tratados como texto. Blade escapa los datos mostrados.
  - Pruebas: php vendor/phpunit/phpunit/phpunit --filter CustomerProfilesTest aprobó 13 pruebas y 172 aserciones; suite completa php vendor/phpunit/phpunit/phpunit aprobó 30 pruebas y 269 aserciones. Incluye duplicados al editar, validación, permisos HTTP/directos, aislamiento, recuperación histórica, reversión por fallo, más de 500 registros, migración con datos previos y contenido escapado. Pint y npm.cmd run build aprobados; revisión independiente estática sin hallazgos accionables.
  - Puesta al día local: copia SQLite consistente storage/app/backups/before-rf001-bbe4f89d7874.sqlite y manifiesto de conteos/huellas; migrate --pretend mostró únicamente la adición de phone/email. php artisan migrate --force --no-interaction aplicó esa migración como lote 3. Integridad SQLite y huellas/conteos de nueve tablas coinciden antes/después. La base real tenía cero clientes y observaciones; la preservación de perfiles con datos se verifica en la prueba de migración. No se crearon cuentas ni datos comerciales ficticios.
  - La verificación acredita RF-001 en SQLite local; no acredita carga productiva, MySQL operativo ni aceptación humana. Direcciones, facturación/crédito automático y demás módulos conservan sus tareas.

### [HECHO] RF-002 — Asociar varias direcciones de entrega a un cliente

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF02; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05; autorización explícita del 2026-11-04 de implementar únicamente RF-002; DEC-2026-11-04-07
- **Contexto propietario:** ClientesAdministracion (frontera propuesta)
- **Dependencias:** RF-001
- **Alcance:** Registrar, consultar y mantener múltiples destinos de entrega vinculados a un mismo cliente.
- **Fuera de alcance:** Geocodificación o proveedor de mapas elegido sin una decisión del proyecto.
- **Invariantes:** Cada dirección pertenece a su cliente; un pedido o despacho no debe recibir por error una dirección de otro cliente.
- **Contratos y archivos previstos:** DeliveryAddress, AddressRecords, ManageAddresses y contrato publicado DeliveryAddresses en src/ClientesAdministracion; SqlDeliveryAddresses, CustomerAddressController y vistas. Integración de RegisterOrder/OrderRegistration/SqlOrderRegistration, OrderFulfillment/MoveStock/InventoryStore y sus adaptadores; migraciones aditivas 2026_11_04_220000 y 220100; selectores en operaciones y tests/Feature/CustomerAddressesTest.php.
- **Criterios de aceptación:** Un cliente conserva al menos dos direcciones diferenciables; se pueden consultar y actualizar sin sobrescribir las otras; los recorridos de pedido y entrega seleccionan el destino correcto. Se define cómo conservar el destino histórico si cambia una dirección.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** ninguno en RF-002; tarea cerrada. Seleccionar con el usuario la próxima tarea, sin iniciar otro requisito automáticamente.
- **Evidencia actual:** RF-002 implementado el 2026-11-04 bajo DEC-2026-11-04-07:
  - Ficha del cliente con varias direcciones identificables, alta, consulta, edición y desactivación. Se prueban dos domicilios del mismo cliente y edición aislada sin modificar otros destinos ni el historial crediticio. Se conservan etiqueta, domicilio, localidad/provincia y datos opcionales; ninguna dirección se elimina desde la aplicación.
  - Cinco rutas de direcciones y controles clients.manage en HTTP/casos de uso. Las opciones activas se consultan también con los permisos de vendedores/depósito, sin mostrar crédito. Se rechazan direcciones ajenas o inactivas en servidor, aun enviando POST directo.
  - Selectores en pedidos/despachos: al cambiar cliente o línea se borra la selección anterior y se cancelan consultas desactualizadas. Cada pedido guarda la copia del destino seleccionado y cada despacho guarda su destino en el contenido firmado. La consulta de pedidos y el historial de movimientos muestran esas copias. Cambios posteriores del domicilio no alteran operaciones previas.
  - Puede registrarse un pedido con destino por definir; un despacho normal debe resolverlo antes de confirmar. Un retiro de propietarios identificado puede no tener destino externo, conservando los controles administrativos y la auditoría. Las copias confirmadas siguen disponibles aunque su dirección de origen se desactive; elegir un destino nuevo exige una dirección activa del mismo cliente.
  - Reintentos: se consulta la operación previa antes del domicilio mutable. Se verifica que editar/desactivar una dirección no duplique pedidos ni despachos. Una fixture de despacho histórico sin delivery_destination y con digest anterior conserva firma, stock/saldo y resultado al reintentar; el verificador admite firmas anteriores sin reescribirlas.
  - Verificación: php vendor/phpunit/phpunit/phpunit aprobó 46 pruebas y 458 aserciones; incluye 16 pruebas de CustomerAddressesTest y la regresión completa de RF-001, permisos e inventario. Pint --test, npm.cmd run build, node --check y revisión estática independiente aprobados. Se verifican reversión por fallos, permisos, ownership, contenido escapado y migraciones con datos previos. No se realizó aceptación humana ni prueba visual en navegador.
  - Base local: copia SQLite consistente storage/app/backups/before-rf002-2caf2151d133.sqlite y manifiesto de conteos/huellas antes de migrar. migrate --pretend mostró únicamente las dos adiciones de RF-002; migrate --force --no-interaction las aplicó en el lote 4. quick_check y diez tablas comparadas coinciden con sus datos previos. La base real tenía cero clientes, pedidos y movimientos: los casos con información previa se verifican en las pruebas; no se crearon datos ficticios en la base real. inventory:audit-verify conserva secuencia 0.
  - Plan y visor actualizados. El cierre acredita RF-002 en SQLite local; MySQL operativo, carga productiva, rutas/documentos y demás módulos conservan sus alcances y tareas.

### [PARCIAL] RF-003 — Impedir despachos sin validación administrativa de facturación y crédito

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF03; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** ClientesAdministracion (frontera propuesta)
- **Dependencias:** ACC-001, RF-007
- **Alcance:** Nadia registra/verifica facturación, crédito y condiciones de despacho mediante permiso configurable; la liberación exige validación previa completa.
- **Fuera de alcance:** Generar facturas fiscales o decidir límites de crédito no informados.
- **Invariantes:** Una salida por despacho requiere validación de facturación y estado crediticio aplicable a esa operación; ocultar un botón no sustituye el control del caso de uso.
- **Contratos y archivos previstos:** Propuesta: contrato publicado de autorización de despacho, caso de uso administrativo y verificación desde inventario/logística; pruebas de permisos y concurrencia.
- **Criterios de aceptación:** Un despacho sin validación, con facturación no validada o crédito no validado es rechazado sin salida de stock; con ambas validaciones y permisos correctos puede continuar; queda identificada la administración que validó. Se prueban accesos directos y cambios concurrentes según la vigencia que se acuerde.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-11
- **Próximo paso:** completar el ciclo de vigencia/revocación según el proceso definitivo y verificar integración con el módulo comercial completo
- **Evidencia actual:** La base de INV-001 guarda ambas validaciones y condiciones; el caso de uso rechaza despachos sin ellas y obtiene bloqueo de la autorización durante la transacción. No se integra ni genera facturación fiscal; quedan recorridos comerciales completos por ampliar.
  - Refactorización del 2026-11-11: listado paginado y búsqueda Livewire por número o cliente, con facturación y crédito separados por pedido confirmado o parcial. Al revisar un pedido se muestran cliente, artículos, cantidades, validación y condiciones guardadas; el formulario usa el caso de uso y la autorización existentes. QueryDispatchAuthorizations compone los contratos de cada contexto según DEC-2026-11-11-04. Se comprueban aislamiento entre pedidos, recuperación de casillas y condiciones, rechazo tras pérdida de permiso y estados no autorizables, y consulta más allá de 200 pedidos. Regresión: 30 pruebas y 381 aserciones; Pint aprobado. Ensayo en navegador con SQLite temporal: selección, foco, guardado Livewire, recuperación de datos, botón de 48 px y ausencia de desbordamiento horizontal a 390 px. La mejora de interfaz no cierra las definiciones pendientes de vigencia y revocación.

### [PENDIENTE] RF-004 — Generar y gestionar formularios Rentas 322 y 344 para entidades públicas

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF04; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** ClientesAdministracion (frontera propuesta)
- **Dependencias:** RF-001, RNF-003
- **Alcance:** Generar y gestionar los SR 322/344 de Misiones a partir de modelos y reglas oficiales aplicables. La referencia del usuario a ARCA se conserva; ATM Misiones publica los manuales de esos formularios.
- **Fuera de alcance:** Presentación automática ante un organismo o certificación tributaria no solicitada.
- **Invariantes:** Relacionar formulario, cliente público y operación; la denominación del adjunto no acredita vigencia ni cumplimiento tributario.
- **Contratos y archivos previstos:** Propuesta: caso de uso documental, contrato de generación y adaptador de formato en ClientesAdministracion; datos, almacenamiento y UI por definir.
- **Criterios de aceptación:** Una operación con entidad pública permite generar y recuperar cada formulario aplicable con los datos acordados; se prueba el tratamiento de datos faltantes y sus permisos. La comparación contra modelos oficiales vigentes y la validación administrativa forman parte del cierre.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** al iniciar este módulo, revisar ejemplares/plantillas y modelos oficiales vigentes; no volver a pedirlos para avanzar con inventario
- **Evidencia actual:** Requisito RF04 incorporado desde la fuente conservada; sin implementación ni pruebas funcionales específicas observadas. Dependencias, contratos y casos de prueba son descomposición técnica del plan, no decisiones comerciales adicionales.
