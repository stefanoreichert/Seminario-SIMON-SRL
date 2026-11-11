# Arranque e importación

Fuentes actuales: papel, Excel y otro sistema. El usuario exportará después. Orden propuesto de carga: usuarios/roles, catálogo, clientes/CUIT, stock físico contado por lote/fabricación, PP vigentes y tandas en proceso; luego histórico y cuenta corriente conciliada.

## Tareas

### [PENDIENTE] DAT-001 — Conciliar e importar datos existentes por etapas sin duplicar stock ni pedidos

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Migración y puesta en marcha
- **Dependencias:** ACC-001, INV-001
- **Alcance:** Preparar inventario de fuentes, corte de saldos y mapeo de catálogo/clientes/stock/PP/histórico; importar cuando estén las exportaciones.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Conservar identificadores/orígenes, detectar CUIT/códigos repetidos, validar cantidades por lote/fecha y conciliar totales antes/después. No sumar como terminados un stock ya cargado inicialmente. Conservar fuente, fecha de corte y evidencias de rechazos.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-11
- **Próximo paso:** esperar las exportaciones pospuestas; mientras tanto usar carga inicial controlada sin inventar datos reales
- **Evidencia actual:** Trabajo registrado a partir de SRS-ACL-2026-11-04; la importación de datos reales sigue pendiente. El 2026-11-11 el usuario pidió un ejemplo de cada sector para presentar el sistema: el comando demo:create agrega datos ficticios identificados como DEMO mediante casos de uso, con referencias de operación estables y sin sobrescribir datos existentes. Se creó el cliente DEMO · Calzados del Sur (#6), su dirección, un artículo, el pedido NP-000001 (20 pares, uno entregado y 19 pendientes), una tanda con cuatro pares en proceso, stock total de 12 pares, ocho códigos individuales (uno despachado y uno seleccionado), un insumo vencido, autorización, movimientos auditados y cinco asientos financieros ficticios (saldo 114500 ARS). Usuario demo.operacion@example.test y rol DEMO · Operación sin access.manage; se conservaron cuentas y roles reales. Cuatro pruebas y 55 aserciones verifican repetición sin duplicados, preservación de datos/permisos, rechazo de colisiones, reversión, autorización y visualización de la muestra en los módulos; la auditoría MySQL quedó verificada en secuencia 3. La migración financiera fue revisada y aprobada tras respaldo privado, como registra FIN-001. La muestra no constituye una importación ni un saldo inicial comercial y no cierra DAT-001.

### [HECHO] DAT-002 — Configurar MySQL y la base simonsrl conservando los datos locales

- **Capa:** SOLICITADO
- **Fuente:** solicitud del usuario del 2026-11-10 «utiliza para el sistema mysql, la base de datos simonsrl»; DEC-2026-11-10-01
- **Contexto propietario:** plataforma y puesta en marcha
- **Dependencias:** INI-002
- **Alcance:** establecer MySQL como conexión del sistema y simonsrl como base; alinear configuración y plantilla; comprobar servidor, esquema y conservación de los datos locales existentes.
- **Fuera de alcance:** importar fuentes comerciales externas, crear datos ficticios o acreditar rendimiento y despliegue productivos.
- **Invariantes:** conservar identificadores, credenciales, permisos y auditoría; no sobrescribir una base destino con datos ni ejecutar pruebas destructivas sobre simonsrl.
- **Contratos y archivos previstos:** .env local, .env.example, config/database.php, config/queue.php, composer.json; SqlInventory/SqlTraceability, migración add_order_workflow y pruebas de trazabilidad; documentación existente del proyecto.
- **Criterios de aceptación:** conexión efectiva mysql sobre simonsrl, migraciones aplicadas sin pérdida de datos, permisos/auditoría conservados y comprobaciones pertinentes aprobadas; registrar cualquier impedimento concreto.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-10
- **Última actualización:** 2026-11-10
- **Próximo paso:** ninguno para este cambio; conservar MySQL/base simonsrl en las próximas tareas. DAT-001 y los requisitos funcionales mantienen su alcance propio.
- **Evidencia actual:**
  - Confirmados PHP 8.4.23, pdo_mysql y Laravel 13.39.0. La restricción inicial de red local se resolvió ejecutando las comprobaciones autorizadas fuera del sandbox. Servidor XAMPP MariaDB 10.4.32 en 127.0.0.1:3306; simonsrl existía vacía. .env, .env.example y valores por defecto usan mysql/simonsrl; colas alineadas y sesión SQL en UTC (+00:00). Composer ya no crea SQLite al preparar un proyecto nuevo; no se cambiaron dependencias ni claves de aplicación/auditoría.
  - Respaldo consistente storage/app/backups/before-mysql-20261110.sqlite con integrity_check=ok; SQLite original conservado. Ocho migraciones aplicadas y metadatos originales de lotes preservados. Transferencia transaccional con comparación de contenido de 23 tablas y manifiesto de conteos/huellas en storage/app/backups/mysql-transfer-20261110.json. Usuario, hash de contraseña, rol, doce permisos y asignación coinciden con el origen. Caché, bloqueos de caché y sesiones transitorias no se copiaron. Los intentos iniciales encontraron esos datos transitorios y se revirtieron antes de la transferencia definitiva.
  - Compatibilidad verificada: los nuevos movimientos almacenan y firman la misma fecha UTC en formato SQL de segundos, aceptado por la columna timestamp; no se reescriben firmas anteriores. SqlTraceability detecta JSON inválido antes de filtrar para que MariaDB no lo presente como cero coincidencias. Las pruebas que modifican disparadores inicializan su propia base antes de cada caso, evitando la confirmación implícita de DDL de MySQL; se comprueba también el rechazo SQL de fechas inválidas.
  - Regresión final: php artisan test --compact con SQLite en memoria, 117 pruebas/1447 aserciones aprobadas. Sobre la conexión mysql, en simonsrl_test_20261110_mysql: FactoryOperationsTest, TraceabilityTest, FilamentManagementTest, OrderOriginsTest y PendingOrdersTest, 53 pruebas/748 aserciones aprobadas. Cubre altas, parciales, idempotencia, permisos, panel, pedidos, búsqueda, firmas y contenido alterado. La base temporal se eliminó al terminar; no se cargaron datos comerciales ficticios en simonsrl.
  - El ensayo de reversión detectó que MySQL reutiliza el índice compuesto de pedidos para la clave foránea de cliente. El down de add_order_workflow repone primero un índice de customer_id cuando falta. En la base temporal aprobaron migrate, rollback --step=1 con clave foránea/índice conservados, nueva aplicación, migrate:reset de las ocho migraciones y nueva aplicación completa. El nombre y el up de la migración histórica se conservan; esta corrección no ejecuta DDL sobre simonsrl.
  - Comprobación real final: Laravel informa mysql, simonsrl y +00:00; migrate --pretend no tiene migraciones pendientes; un usuario, un rol, doce permisos, una asignación, cero clientes/pedidos/movimientos y cuatro disparadores de protección. inventory:audit-verify conserva secuencia 0/digest inicial; solo incrementa el bloqueo técnico. Pint --dirty --test y composer validate --no-check-publish aprobados. Plan/visor usan esquema 1 y reutilizan el servidor 8788 del mismo projectId b5af23625b6cb1c1.
  - Límites: el ensayo corresponde al servidor MariaDB de XAMPP mediante mysql, no a Oracle MySQL ni a carga/concurrencia de producción. db:show de Laravel no funciona en este servidor porque consulta performance_schema.session_status, tabla ausente en MariaDB 10.4; la conexión, el esquema y los conteos se comprobaron mediante consultas directas y Artisan. Los requisitos de rendimiento, operación externa e importación comercial conservan sus tareas; el validador del plan no sustituye estas pruebas.
