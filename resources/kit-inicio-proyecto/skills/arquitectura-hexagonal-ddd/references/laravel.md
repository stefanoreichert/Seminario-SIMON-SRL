# Perfil Laravel

Aplicar cuando Laravel esté confirmado. Leer `composer.json`, `composer.lock`, manifiestos frontend y convenciones existentes. Consultar versiones compatibles antes de introducir APIs. Livewire, Filament, AdminLTE, Blade, una SPA y el motor SQL son decisiones del proyecto, no requisitos de este perfil.

- Estructura obligatoria: `src/<Contexto>/{Domain,Application,Infrastructure,UI}` desde la raíz del repositorio, salvo otra raíz elegida expresamente por el usuario y registrada con su solicitud y fecha. No crear `app/Contexts`, `app/Domain` ni contextos bajo `App` para aprovechar el autoload existente.
- Configurar un prefijo PSR-4 propio hacia `src/` en Composer y alinear namespaces, pruebas y registro de adaptadores/migraciones con esa ruta. `app/Providers`, bootstrap y otros puntos de entrada técnicos solo conectan dependencias; las reglas de negocio permanecen en src. El autoload se adapta a la arquitectura. Referencia: [PSR-4 en Composer](https://getcomposer.org/doc/04-schema.md#psr-4).
- Domain y Application son PHP sin facades, Eloquent, helpers globales, tipos HTTP, paginadores Laravel ni SDKs. Pasar datos simples/DTOs y contratos de identidad, reloj, logging, transacción y persistencia cuando sean necesarios.
- Eloquent, migraciones, seeders y gateways pertenecen a Infrastructure. Controladores, recursos administrativos y componentes Livewire son adaptadores de entrada; también CRUD, bulk actions, imports y relaciones que escriben invocan casos de uso.
- El proveedor conecta implementaciones a interfaces; el contenedor no sustituye dependencias explícitas dentro del núcleo. Un evento de dominio puro no implementa contratos Laravel; el adaptador coordina su entrega tras el commit cuando corresponda.
- Usar puertos transaccionales y consultas/bloqueos de infraestructura según invariantes y motor. Un test SQLite no acredita el comportamiento concurrente del motor SQL de producción.
- Pruebas según lo instalado: PHPUnit/Pest para dominio/casos/adaptadores, pruebas de acciones y permisos de UI si existen y build del frontend cuando cambie. No ejecutar migraciones o seeders sobre datos existentes durante la preparación del proyecto.
