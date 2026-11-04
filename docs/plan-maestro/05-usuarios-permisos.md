# Gestión de usuarios, roles y permisos

La asignación de facultades se realiza desde el módulo, no por nombres de personas. Sandra y Gustavo son usuarios con S/G. Los permisos de catálogo se configuran aquí. Fuente y decisiones: [aclaraciones](03-decisiones-relevamiento.md).

## Tareas

### [HECHO] ACC-001 — Gestionar usuarios, roles y permisos configurables

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Acceso
- **Dependencias:** INI-002
- **Alcance:** Crear/editar/desactivar usuarios, crear/editar roles y asignar permisos/roles; identificador de vendedor único S/G/F; acceso desde el panel existente.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Usuario sin permiso no modifica asignaciones, incluso por POST directo. Se conserva al menos un administrador activo. Contraseñas almacenadas con hash, desactivación revoca uso de operaciones y roles se aplican sin nombres codificados. Crear cuentas reales requiere sus datos, no credenciales inventadas.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** asignar el primer administrador con access:bootstrap a una cuenta existente y configurar las cuentas/roles reales desde el módulo
- **Evidencia actual:** Implementados ManageAccess/SqlAccess y /gestion/usuarios; altas/edición/desactivación, roles y permisos, identificador vendedor, hash de contraseña y protección del último administrador. FactoryOperationsTest verifica denegación HTTP/directa, asignación, hash, identificador y desactivación. Suite final: 16 pruebas y 87 aserciones aprobadas; Pint aprobado. No se inventaron cuentas personales ni credenciales.
