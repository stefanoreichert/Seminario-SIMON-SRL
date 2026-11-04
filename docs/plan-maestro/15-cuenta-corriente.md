# Cuenta corriente monetaria

El saldo de pares por entregar y la deuda del cliente son magnitudes diferentes. El usuario aclaró que puede quedar saldo en cuenta corriente después de un despacho. Este módulo se conserva separado de la operación física; no se inventan cobros, moneda o reglas contables.

## Tareas

### [PENDIENTE] FIN-001 — Mantener cuenta corriente financiera independiente del saldo de mercadería

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** ClientesAdministracion / administración financiera por precisar
- **Dependencias:** ACC-001, RF-001
- **Alcance:** Registrar y consultar deuda monetaria, movimientos y conciliación con las fuentes existentes; un despacho no equivale a cobro.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Una entrega completa puede dejar deuda monetaria. Las operaciones de cobro/ajuste afectan solo la cuenta pertinente y conservan trazabilidad; definir moneda y saldos iniciales al iniciar este módulo.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** retomar el módulo financiero con los saldos/exportaciones que administración aporte; el corte físico no cancela deuda
- **Evidencia actual:** Trabajo registrado a partir de SRS-ACL-2026-11-04; sin implementación de este módulo todavía.
