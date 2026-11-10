# Ficha de Seminario SIMON SRL

Preparación iniciada el 2026-11-04. La postergación inicial de definiciones de negocio se actualizó ese mismo día al recibir SRS-2026-11-04: el usuario aportó RF01–RF17 y RNF 01–RNF 03 y solicitó incorporarlos al plan maestro (DEC-2026-11-04-04).

## Stack observado

| Componente | Evidencia y versión |
| --- | --- |
| Backend | PHP CLI 8.4.23; Laravel 13.39.0, comprobados con `php --version` y `composer show --direct`. |
| Administración | Filament 5.11.0; panel `admin` configurado en `app/Providers/Filament/AdminPanelProvider.php`. |
| UI | Livewire 4.4.8; Blade; Tailwind CSS 4 y Vite 8 declarados en `package.json`. |
| Persistencia | MySQL confirmado por el usuario el 2026-11-10, base `simonsrl`; Laravel usa la conexión `mysql`. El servidor local disponible en XAMPP es MariaDB 10.4.32, compatible con ese conector. Eloquent/Query Builder permanecen en Infrastructure. |
| Pruebas | PHPUnit 12.5.42; dos pruebas iniciales de ejemplo; FactoryOperationsTest agrega cobertura del primer flujo autorizado. |
| Herramientas del kit | Python 3.13.15 disponible; mínimo 3.9, biblioteca estándar. Node comprueba la sintaxis del visor. |
| Entorno local | Windows, PowerShell y directorio de trabajo en XAMPP. Despliegue y operación por definir. |

Raíz del código propio: `src/`; prefijo Composer/PSR-4: `Simon\\`; sin excepción de ubicación. La composición técnica conserva `App\\` en app. Fuentes conocidas y pendientes: [índice de fuentes](fuentes/README.md).

Perfil de adaptación: `laravel`. Se conserva el stack existente; las herramientas Python son auxiliares de desarrollo.

Interfaz común: panel Filament admin con menú lateral izquierdo, grupos Comercial/Fábrica/Administración y componentes/tema compartidos. La decisión del usuario se conserva en DEC-2026-11-04-08 y AGENTS.md; aplica a las RF posteriores, una por vez. UI-001 incorpora la integración y sus pruebas; la revisión visual completa sigue pendiente.

## Producto y requisitos recibidos

El alcance aportado cubre clientes y administración, ventas/pedidos, depósito/stock/trazabilidad, logística/postventa y reportes/estadísticas. Se incluyen el máximo de 2 segundos para búsquedas históricas, la interfaz de depósito con mínimo de 14 px y el acceso por roles con auditoría no modificable de movimientos.

La fuente identifica Nadia/Administración, Claudia/Depósito y Pablo/Producción. Sandra y Gustavo también son usuarios vendedores identificados con S y G; F corresponde a Fábrica. Los permisos se asignan mediante el módulo, sin fijarlos por nombre. Los clientes y las entidades públicas son contrapartes; no se deduce un acceso directo de ellos al sistema.

El [registro de requisitos y alcance](plan-maestro/02-requisitos-y-alcance.md) conserva la copia íntegra del adjunto, su hash, la correspondencia de los veinte IDs y las preguntas DEF-01–DEF-14. Las tareas están distribuidas por área en el [plan maestro](plan-maestro/README.md).

## Definiciones pendientes

Precisar permisos por operación, reglas de crédito/facturación, identidad/estados de pedidos, fuentes de producción/costos, semántica de saldos y trazabilidad, dispositivos, documentos y métricas. Validar aplicabilidad de formularios y cómputo del plazo de devoluciones antes de implementar. El motor elegido es MySQL y la base es simonsrl (DEC-2026-11-10-01); faltan el entorno de producción y las condiciones del ensayo de rendimiento.

SRS-ACL-2026-11-04 resolvió las reglas del corte actual y pospuso reportes, hojas de ruta y exportaciones. INI-002 fija contratos para ese corte. DEC-2026-11-05-11 resuelve por delegación los duplicados comerciales activos, la edición solo de borradores y la anulación del saldo tras parciales, conservando entregas. Los detalles de módulos futuros mantienen sus tareas.

## Alcance de esta preparación

Integrar el prompt maestro, el kit portable, tres skills locales, plantillas, documentación, esquema común, validador, reportes y visor exportable/sincronizado. Conservar las reglas Laravel de `AGENTS.md` y los archivos existentes. La autorización posterior «Ok, implementa» cubre el flujo de altas/despachos: ACC-001 e INV-001 incorporan permisos y operaciones. La deuda en cuenta corriente es independiente de los pares pendientes. Uso inicial local en fábrica; WhatsApp se transcribe manualmente y venta Internet queda futura. Auditoría con retención indefinida, protección SQL y firmas; capa externa inmutable pendiente en SEC-001.

Referencias: [arquitectura](arquitectura/README.md), [decisiones](decisiones/README.md), [plan maestro](plan-maestro/README.md) y [kit reutilizable](../resources/kit-inicio-proyecto/README.md).
