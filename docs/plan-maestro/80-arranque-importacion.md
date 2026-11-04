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
- **Última actualización:** 2026-11-04
- **Próximo paso:** esperar las exportaciones pospuestas; mientras tanto usar carga inicial controlada sin inventar datos reales
- **Evidencia actual:** Trabajo registrado a partir de SRS-ACL-2026-11-04; sin implementación de este módulo todavía.
