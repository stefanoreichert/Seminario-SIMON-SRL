# Requisitos, fuentes y alcance

## Fuente conservada

SRS-2026-11-04 corresponde al texto adjunto por el usuario el 2026-11-04 con la solicitud «los requerimentos del sistema son los siguientes, agregalos al plan maestro».

- [Copia íntegra del texto original](fuentes/2026-11-04-requisitos-originales.txt).
- SHA-256 del archivo original y de su copia: `2956ec4d1ce56d8402ca70cca53b07250c64f50c2f8ec99e8f037e556b7b0b59`.
- Contenido recibido: RF01–RF17 y RNF 01–RNF 03, agrupados en cinco módulos funcionales y requisitos transversales.
- El texto original se conserva sin corregir ni completar. Sus encabezados «Actores internos» y «Actores externos» no contienen listas; las menciones posteriores aportan los actores conocidos.
- DEC-2026-11-04-04 actualiza la postergación previa de requisitos de DEC-2026-11-04-03. El pedido actual autoriza incorporarlos al plan, sin iniciar desarrollo funcional.

Actualización posterior: [aclaraciones y decisiones](03-decisiones-relevamiento.md), registradas en DEC-2026-11-04-05. La fuente original permanece intacta; la autorización posterior cubre ACC-001 e INV-001.

## Objetivo y procesos aportados

Digitalizar clientes/crédito y validación administrativa, notas de pedido, existencias por par/lote/partida, entregas parciales, documentos logísticos, devoluciones y análisis de ventas/producción/inventario. La fuente describe registros manuales, problemas de duplicación, búsquedas históricas lentas y falta de registro de movimientos como motivaciones del sistema; no son hallazgos verificados sobre operación real por esta revisión.

## Actores y límites conocidos

| Mención | Alcance que consta en la fuente | Pendiente |
| --- | --- | --- |
| Nadia | Perfil individual de Administración (RNF 03); validación administrativa de RF03. | Matriz exacta de acciones y sustituciones/delegaciones. |
| Claudia | Perfil individual de Depósito (RNF 03), operación y lectura industrial (RNF 02). | Permisos por recorrido y equipo de lectura. |
| Pablo | Perfil individual de Producción (RNF 03). | Operaciones autorizadas y fuente de producción en proceso/Informe de Planta. |
| Sandra / Gustavo | Usuarios vendedores con identificadores S y G, confirmado en aclaraciones. | Asignar sus cuentas y permisos desde el módulo, sin hardcodear nombres. |
| Fábrica | Origen F de pedidos (RF06); empaque origina altas (RF12). | Relación con Producción y mecanismo de integración. |
| Clientes | Datos comerciales, direcciones, pedidos, entregas y devoluciones. | No se solicitó portal ni acceso directo del cliente. |
| Entidades públicas | Contrapartes de operaciones con formularios Rentas 322/344. | Jurisdicción y reglas tributarias aplicables. |
| Propietarios | Mencionados en la justificación de RNF 03 sobre retiros informales. | Registro y autorización de sus retiros; no se les conceden privilegios por esta mención. |

Transportistas, proveedores y organismos pueden intervenir en recorridos, pero la fuente no define sus roles de acceso ni integraciones. No se agregan actores con permisos supuestos.

## Correspondencia de identificadores

Los IDs originales se conservan en Fuente. Los IDs de tarea usan el esquema del kit: RF01 se representa con RF-001 y RNF 01 con RNF-001. Esto no renumera ni reemplaza el requisito original.

| ID original | Tarea del plan | Resultado |
| --- | --- | --- |
| RF01 | [RF-001](10-clientes-administracion.md) | Registrar, consultar y actualizar perfiles comerciales e historial crediticio |
| RF02 | [RF-002](10-clientes-administracion.md) | Asociar varias direcciones de entrega a un cliente |
| RF03 | [RF-003](10-clientes-administracion.md) | Impedir despachos sin validación administrativa de facturación y crédito |
| RF04 | [RF-004](10-clientes-administracion.md) | Generar y gestionar formularios Rentas 322 y 344 para entidades públicas |
| RF05 | [RF-005](20-ventas-pedidos.md) | Cargar múltiples artículos en un solo formulario de nota de pedido |
| RF06 | [RF-006](20-ventas-pedidos.md) | Identificar el origen de las notas mediante S, G y F |
| RF07 | [RF-007](20-ventas-pedidos.md) | Asignar numeración única y controlar estados para prevenir pedidos duplicados |
| RF08 | [RF-008](20-ventas-pedidos.md) | Consultar pedidos pendientes y mercadería adeudada con actualización en tiempo real |
| RF09 | [RF-009](30-deposito-stock-trazabilidad.md) | Vincular lote y partida a cada par despachado y consultar su historial |
| RF10 | [RF-010](30-deposito-stock-trazabilidad.md) | Calcular Stock Disponible + Producción en Proceso − Pedidos Pendientes |
| RF11 | [RF-011](30-deposito-stock-trazabilidad.md) | Identificar cada par y preparar picking con lectores y control de vencimientos |
| RF12 | [RF-012](30-deposito-stock-trazabilidad.md) | Registrar automáticamente altas de empaque y bajas por despacho o retiro |
| RF13 | [RF-013](40-logistica-postventa.md) | Emitir e imprimir remitos, rótulos y hojas de ruta por zona |
| RF14 | [RF-014](40-logistica-postventa.md) | Confirmar despachos parciales y reducir el saldo hasta completar el pedido |
| RF15 | [RF-015](40-logistica-postventa.md) | Registrar devoluciones, notas de pedido negativas y reintegro de stock |
| RF16 | [RF-016](50-reportes-estadisticas.md) | Generar tablero e informes de ventas por curva, planta y rentabilidad por rubro |
| RF17 | [RF-017](50-reportes-estadisticas.md) | Consultar antigüedad, obsolescencia, rotación por variante y distribución geográfica |
| RNF 01 | [RNF-001](60-requisitos-no-funcionales.md) | Responder búsquedas históricas de lotes y partidas en un máximo de 2 segundos |
| RNF 02 | [RNF-002](60-requisitos-no-funcionales.md) | Ofrecer interfaz de depósito legible, de alto contraste y operable con lectores |
| RNF 03 | [RNF-003](60-requisitos-no-funcionales.md) | Aplicar roles individuales y auditoría no modificable en cada alta y baja de stock |

## Definiciones abiertas y resolución

Las aclaraciones posteriores resuelven o posponen los puntos iniciales. Lo postergado no se convierte en exclusión ni impide el corte autorizado.

| ID | Estado actual |
| --- | --- |
| DEF-01 | Usuarios/roles/permisos configurables. Sandra/Gustavo también ingresan con S/G; asignación granular desde el módulo. |
| DEF-02 | CUIT único obligatorio; historial y condiciones manuales a cargo de administración. |
| DEF-03 | Misiones; precisión oficial ATM para SR322/344. Ejemplares de formularios se revisarán en el módulo impositivo. |
| DEF-04 | Catálogo existente; numeración global y estados aceptados. Duplicado comercial y edición/anulación tras parciales siguen abiertos. |
| DEF-05 | Lote y fabricación prioritarios; lote compartido por tanda; IRAM 3610 informada. Sin partida adicional impuesta. |
| DEF-06 | Disponible físico en depósito; proceso termina por la cantidad dada de alta. Fuente manual de tandas en el corte; proyección de necesidades pendiente. |
| DEF-07 | Sin lectores actuales; captura manual. Avisos antes/después del vencimiento sin bloqueo automático; anticipación configurable al desarrollar insumos. |
| DEF-08 | Alta de terminados y baja/PP al despachar autorizadas; retiro de propietarios identificable; cuenta corriente monetaria separada. |
| DEF-09 | Remitos y hojas actuales manuales; hojas pospuestas al módulo específico. |
| DEF-10 | Regla operativa desde fabricación, aprobación de Claudia, reintegro solo si apto, nota negativa sin reemplazo. Validación legal separada en su módulo. |
| DEF-11 | Reportes y fórmulas expresamente pospuestos; no condicionan el corte actual. |
| DEF-12 | Todos los actores simultáneos; PP/stock máximo cinco segundos, trazabilidad dos segundos. Volumen representativo real se precisa al contar con exportaciones. |
| DEF-13 | Corte manual con fuente 16 px y controles grandes; validación industrial y lectores futuros pendientes. |
| DEF-14 | Retención indefinida; append-only y firmas locales implementadas como base; evidencia externa inmutable pendiente en SEC-001. |

## Secuencia de trabajo propuesta

Las dependencias de las tareas constituyen una propuesta técnica, no una prioridad comercial o fecha comprometida. Se revisan al precisar contratos en INI-002.

1. Cerrar contratos del corte y gestionar permisos configurables: INI-002 y ACC-001; separar la base de auditoría de su aceptación integral RNF-003/SEC-001.
2. Clientes/direcciones, carga/origen y numeración de pedidos: RF-001, RF-002, RF-005, RF-006 y RF-007.
3. Validación administrativa y formularios: RF-003 y RF-004.
4. Movimientos, identificación de pares y trazabilidad: RF-012, RF-011 y RF-009.
5. Entregas parciales, documentos y PP: RF-014, RF-013 y RF-008; fórmula de necesidades en RF-010.
6. Devoluciones y explotación analítica: RF-015, RF-016 y RF-017.

La medición RNF-001 se verifica con RF-009 y la evaluación RNF-002 con RF-011 y sus recorridos de depósito. Sus criterios se consideran desde el diseño de esas tareas; el orden no permite omitirlos durante la implementación. La producción en proceso y sus informes requieren un contrato/fuente por definir; no autorizan construir un módulo de planta adicional por inferencia.

## Estado observado y verificación

La revisión inicial del 2026-11-04 encontró el esqueleto Laravel. Posteriormente el corte autorizado incorporó módulos/casos de uso, migración aditiva y pruebas de operaciones; el avance de cada requisito figura en su tarea y en INV-001. `php artisan route:list --except-vendor` muestra la bienvenida como ruta propia. Las pruebas originales eran de ejemplo; FactoryOperationsTest cubre el corte actual, sin acreditar módulos futuros.

Los estados y reportes se calculan desde las tareas Markdown. Validar con `python -X utf8 -B .agents/skills/manage-project-roadmap/scripts/roadmap.py check` y regenerar el [visor](visualizacion.html) con `tree`. La validación comprueba estructura y dependencias; la aceptación comercial y la ejecución funcional siguen pendientes.
