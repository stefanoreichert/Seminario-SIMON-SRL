# Decisiones del relevamiento y corte autorizado

Fuente SRS-ACL-2026-11-04: respuestas del usuario a las preguntas agrupadas, posteriores a SRS-2026-11-04. [Registro conservado](fuentes/2026-11-04-aclaraciones.txt). DEC-2026-11-04-05 registra estas decisiones; las afirmaciones del usuario y las decisiones técnicas delegadas se distinguen abajo.

## Respuestas y efecto en el plan

| Punto | Decisión registrada |
| --- | --- |
| 1. Usuarios y permisos | La asignación se realiza desde un módulo de gestión de usuarios, roles y permisos. Sandra y Gustavo ingresan como vendedores y registran pedidos con S y G; los nombres no se codifican como autorización. |
| 2. Clientes y crédito | CUIT único y obligatorio. Nadia verifica y carga historial crediticio y condiciones de autorización de despachos, mediante los permisos asignados al perfil correspondiente. |
| 3. Jurisdicción tributaria | El usuario indicó ARCA, provincia de Misiones. La fuente oficial de los formularios SR 322/344 consultada corresponde a ATM Misiones; se conserva la expresión original y se registra esta precisión. |
| 4. Sentido de modelos de formularios | Se refiere a ejemplares o plantillas de los formularios 322/344, vacíos o ya utilizados. No se refiere a modelos de calzado. Su obtención se difiere al trabajo impositivo. |
| 5. Catálogo | Ya existe una lista de modelos, colores y talles. Las facultades de alta/modificación se asignarán desde roles y permisos; la importación de esa lista se realizará después. |
| 6. Numeración, estados y duplicados | Se aceptaron numeración global y estados Borrador, Confirmado, Parcial, Entregado y Anulado. La duda de duplicados distingue un reintento accidental de un nuevo pedido legítimo con los mismos artículos. Esa regla y la edición/anulación después de un parcial siguen abiertas; no se bloquean pedidos por mera coincidencia de contenido. |
| 7. Stock y producción | Disponible representa pares físicos en depósito. Los pares terminados dejan de estar en proceso cuando Claudia confirma el alta; se conserva trazabilidad por cantidad para altas parciales. |
| 8. Lectores y vencimientos | No hay lectores. El lote existente identifica una tanda y no un par único. La fase inicial permite captura manual. Los insumos generan advertencias antes y después del vencimiento, sin bloqueo automático por vencimiento; la anticipación será configurable en su módulo. |
| 9. Trazabilidad y norma | Datos prioritarios: número de lote y fecha de fabricación. Norma informada: IRAM 3610. No exigir una partida distinta no confirmada. Esto no acredita certificación del software ni lectura completa de la norma. |
| 10. Autorización de implementación y cuenta corriente | El usuario indicó «Ok, implementa» para alta al confirmar terminados y baja de stock/reducción del saldo de mercadería al confirmar despacho. El saldo monetario de cuenta corriente es independiente y no se cancela al entregar pares. |
| 11. Retiro de propietarios | Se permite identificar el movimiento como retiro de propietarios. No se deduce una exención de registro, auditoría o autorización administrativa. |
| 12. Remitos y hojas de ruta | Actualmente ambos son manuales. Las hojas de ruta se difieren a un módulo específico futuro. La postergación no elimina RF13 del alcance histórico. |
| 13. Devoluciones | Plazo operativo informado: seis meses desde fabricación indicada en el calzado. Claudia aprueba. Solo vuelve al stock vendible si está en condiciones; de lo contrario no. La nota negativa registra la devolución y no genera reposición pendiente. La aplicabilidad legal se verifica al trabajar ese módulo. |
| 14. Reportes | Curva, rubro, rentabilidad, obsolescencia y rotación se analizarán más adelante. RF16/RF17 siguen pendientes y no bloquean el corte de inventario autorizado. |
| 15. Arranque e importación | Hay papel, Excel y otro sistema; las exportaciones se realizarán después. Propuesta de arranque: usuarios/roles; catálogo existente; clientes con CUIT; conteo de stock por lote/fecha; pedidos pendientes vigentes; luego histórico y migración monetaria conciliada. |
| 16. Canales y alcance de red | Uso inicial dentro de fábrica; los pedidos recibidos por WhatsApp se registran manualmente en el sistema. Venta por Internet es futura; no se presume integración automática con WhatsApp. |
| 17. Concurrencia | Deben poder operar simultáneamente todos los actores habilitados. El escenario inicial debe representar Administración, Depósito, Producción y vendedores S/G; falta volumen real de datos para dimensionar el ensayo. |
| 18. Actualización y rendimiento | Se aceptó máximo de cinco segundos para reflejar PP/stock entre puestos, conservando dos segundos para búsqueda histórica de trazabilidad. |
| 19. Retención | Historial de auditoría conservado indefinidamente. Sin purga ni edición/borrado desde la aplicación; desactivar usuarios conserva referencias históricas. |
| 20. Seguridad delegada | Decisión técnica: permisos de mínimo privilegio, movimientos y auditoría atómicos, tablas append-only, firmas HMAC encadenadas con clave fuera de la base y verificador. Para resistir borrado/truncamiento por un administrador del servidor se requiere además copia/cierre externo inmutable con credenciales separadas; esa infraestructura queda explícita como SEC-001, sin afirmar que está desplegada. |

## Precisión de fuentes normativas

[ATM Misiones — manuales de Ingresos Brutos](https://atmisiones.gob.ar/ingresos-brutos-manuales/) publica los accesos/manuales de los certificados 322 y 344. La referencia inicial a ARCA se conserva como dato aportado; no se genera ni presenta un formulario fiscal durante este corte.

El usuario informó IRAM 3610. [IRAM — certificación de elementos de protección personal](https://www.iram.org.ar/servicio/elementos-de-proteccion-personal/) describe su actividad de certificación. Guardar lote y fabricación no acredita por sí solo conformidad con todos los requisitos de esa norma.

## Primer corte de implementación

ACC-001 implementa gestión configurable de usuarios/roles/permisos. INV-001 implementa el recorrido mínimo de operaciones autorizado: catálogo y clientes necesarios para registrarlo, notas confirmadas con S/G/F y número global, validación administrativa, registro de tandas en proceso, altas de terminados, stock inicial auditado y despachos parciales con lote/fecha. Los saldos actualizados son cantidades de mercadería, no dinero.

La administración de permisos permite habilitar las funciones para Nadia, Claudia, Pablo, Sandra y Gustavo sin privilegios ligados a su nombre. No se crean cuentas reales, contraseñas compartidas ni asignaciones personales por inferencia.

Las importaciones, cuenta corriente monetaria, devoluciones, lectores, vencimientos de insumos, documentos logísticos y reportes conservan sus tareas y el alcance confirmado, sin atribuirles implementación por el cierre de INV-001. Los estados Borrador/Anulado y la edición posterior a parciales se completan en RF-007; la entrada de este corte crea pedidos Confirmados y avanza a Parcial/Entregado.

## Puesta en marcha y datos iniciales

1. Aplicar la migración aditiva después de la copia de seguridad local.
2. Asignar el primer administrador a un usuario existente con `php artisan access:bootstrap correo@empresa`. El comando se niega si ya existe un administrador activo.
3. En `/gestion/usuarios`, definir roles y crear/asignar las cuentas individuales y sus identificadores S/G.
4. Cargar el catálogo existente y clientes con CUIT; después, stock físico contado por lote y fabricación mediante Stock inicial.
5. Cargar los pedidos vigentes por su saldo real y las tandas que continúan en proceso. No duplicar existencias iniciales como altas nuevas.
6. Conciliar y exportar/importar los históricos y la cuenta corriente en DAT-001 y FIN-001.

Las cantidades, usuarios y fechas de prueba son sintéticos. Las exportaciones reales y reglas impositivas pospuestas no impiden probar este primer recorrido.

## Protección del historial

Las tablas de movimientos y auditoría rechazan UPDATE/DELETE mediante disparadores SQLite/MySQL. Cada movimiento conserva usuario, fecha/hora UTC, cantidad, tipo, lote, fabricación y referencia de pedido; una firma HMAC encadena su contenido. La verificación se ejecuta con `php artisan inventory:audit-verify`. La operación y su firma se confirman o revierten juntas.

`INVENTORY_AUDIT_KEY` permite una clave dedicada externa a la base. Si no se configura, se usa APP_KEY; conservar las claves históricas y no rotarlas sin una transición de firma. No publicar claves ni almacenar su valor en el historial.

Un usuario con control total del servidor puede eliminar disparadores, restaurar una base anterior o acceder a las claves del mismo servidor. Las firmas detectan alteraciones de contenido cuando la clave no fue comprometida; no prueban por sí solas que nadie truncó un cierre completo. SEC-001 debe desplegar copia/cierres inmutables fuera del servidor y separación de credenciales para cubrir esa amenaza. RNF-003 permanece PARCIAL hasta verificar esa capa y los recorridos futuros.
