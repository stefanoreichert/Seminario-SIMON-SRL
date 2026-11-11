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

### [HECHO] ACC-002 — Unificar y simplificar la gestión de usuarios, roles y permisos

- **Capa:** SOLICITADO
- **Fuente:** Solicitud del usuario del 2026-11-11 en esta conversación
- **Contexto propietario:** Acceso
- **Dependencias:** ACC-001
- **Alcance:** Unificar usuarios, roles y permisos en un panel; presentar listas simples y facilitar la creación y edición de usuarios y roles, asignación de roles y permisos, y desactivación o reactivación de usuarios.
- **Fuera de alcance:** Borrar usuarios o historial, crear cuentas reales o alterar el catálogo de permisos.
- **Invariantes:** Solo access.manage gestiona usuarios; conservar el último administrador activo, la validación y el hash de contraseñas, los identificadores únicos y el historial. Las escrituras pasan por el caso de uso existente.
- **Contratos y archivos previstos:** UI del contexto Acceso, integración Livewire/Filament y pruebas de gestión y autorización existentes.
- **Criterios de aceptación:** Un panel permite alternar entre usuarios y roles; la lista de usuarios muestra nombre, correo, roles y estado, y permite crear, editar, cambiar roles y desactivar o reactivar. La lista de roles permite crear y editar nombres y permisos. Las interacciones de gestión usan Livewire; se verifica autorización en servidor y protección del último administrador, sin pérdida de datos ni historia.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-11
- **Última actualización:** 2026-11-11
- **Próximo paso:** ninguno; tarea cerrada. Configurar usuarios y roles reales con datos autorizados cuando corresponda.
- **Evidencia actual:** Panel único de acceso desde Usuarios, con secciones de usuarios y roles; la URL anterior de roles sigue disponible. Listados con roles, estado y cantidad de permisos; formularios seleccionados para crear y editar cuentas y roles. La desactivación y reactivación se realiza en la edición de usuario; las asignaciones y permisos se guardan mediante ManagementActions/Livewire y ManageAccess, con autorización en servidor. Se comprobó la conservación de contraseña e identidad, aplicación de permisos, protección del último administrador y denegación de operaciones directas Livewire sin permiso. Prerrequisito ACC-001 verificado HECHO. Regresión: 35 pruebas y 454 aserciones aprobadas; Pint aprobado. Después se destacaron las acciones de crear y editar con botones Filament y se reutilizó el tamaño operativo de 48 px; 11 pruebas y 274 aserciones aprobaron tras ese ajuste. No se acredita revisión visual en navegador ni aceptación operativa.
