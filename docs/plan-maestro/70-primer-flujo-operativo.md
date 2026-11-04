# Primer flujo operativo autorizado

La expresión «Ok, implementa» autoriza el recorrido de altas y despachos seleccionado. Se implementan las capacidades mínimas de clientes/catálogo/pedidos para usarlo, sin declarar completos todos esos módulos. Ver [decisiones y puesta en marcha](03-decisiones-relevamiento.md).

## Tareas

### [HECHO] INV-001 — Confirmar altas y despachos parciales con permisos, trazabilidad y auditoría

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** InventarioTrazabilidad, con contratos de ClientesAdministracion y VentasPedidos
- **Dependencias:** ACC-001
- **Alcance:** Recibir pares terminados descontando proceso; cargar stock inicial contado; despachar cantidades de una nota con validación administrativa; reducir stock y PP; etiquetar retiro de propietarios. No registrar cobros ni modificar deuda monetaria.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Altas y bajas conservan lote/fabricación y firma con fecha/hora/usuario/cantidad. Pruebas 10→4→6, reintento y conflicto de token, falta de permisos/validaciones/stock, exceso de saldo, artículo distinto y fallo de auditoría sin efectos parciales. Pedido y stock se actualizan atómicamente. El historial crediticio y condiciones administrativas conservan su evidencia.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** configurar cuentas/roles y cargar el catálogo/clientes/stock inicial real siguiendo DAT-001; ampliar módulos por sus tareas pendientes
- **Evidencia actual:** Implementados casos de uso, puertos/adaptadores, formularios autenticados y 14 rutas de gestión. Alta descuenta proceso; despacho parcial/final reduce stock/pares pendientes con autorización administrativa, lote/fabricación, idempotencia y firma. Historial crediticio conserva versiones y el movimiento conserva copia de las condiciones de autorización. Suite final: 16 pruebas, 87 aserciones; incluye reintentos, conflictos, stock/saldo insuficiente, permisos, datos inconsistentes, fallo de auditoría, disparadores y manipulación tras eliminar un trigger. Build Vite, node --check y Pint aprobados. Se hizo copia consistente SQLite antes de aplicar migración aditiva; migrate:status confirma aplicada. Verificador local sobre la base real devuelve secuencia 0: no se cargaron movimientos de prueba ni usuarios inventados allí. Captura y actualización iniciales están implementadas; volumen real, MySQL operativo, ergonomía industrial y evidencia externa no se acreditan por estas pruebas.
