---
name: arquitectura-hexagonal-ddd
description: Diseñar, implementar y revisar arquitectura hexagonal con DDD en el proyecto, definiendo contextos, puertos, casos de uso y adaptadores según el stack confirmado. Usar para decisiones de arquitectura o trabajo de código autorizado; no selecciona tecnologías ni inicia funcionalidades por sí sola.
---

# Arquitectura hexagonal y DDD

Leer `AGENTS.md`, `docs/proyecto.md` y `docs/arquitectura/README.md` desde la raíz del proyecto. Confirmar stack antes de aplicar perfiles: [Laravel](references/laravel.md), [Flutter](references/flutter.md) o [Go](references/go.md). Leer los pertinentes a los componentes confirmados; para otro stack, documentar la adaptación sin copiar convenciones de un lenguaje distinto. Un sistema puede combinar perfiles sin compartir detalles de sus adaptadores.

## Preparar la arquitectura

- Usar `/src` en la raíz del repositorio para el código propio, siempre salvo elección explícita del usuario. Registrar la raíz efectiva en `.agents/project.json`, ficha y arquitectura; una excepción necesita solicitud y fecha en decisiones. Ni un perfil ni las convenciones existentes autorizan cambiarla. Base: `src/<Contexto>/{Domain,Application,Infrastructure,UI}`; adaptar nombres/empaquetado internos al lenguaje conservando src. No reemplazarla por `app/Contexts`, `app/Domain`, `lib/src` o `internal` en la raíz. Los archivos técnicos de arranque/composición que el framework exija pueden quedar fuera, sin reglas de negocio.
- Partir de actores, lenguaje, procesos, invariantes y propiedad de datos. Derivar contextos de esas reglas; no crearlos por cada pantalla ni suponer microservicios.
- Organizar por contexto y, dentro, Domain, Application, Infrastructure y UI/Presentation. La dirección de dependencias apunta al núcleo; la composición conecta adaptadores con puertos.
- Domain contiene entidades, agregados, value objects, invariantes, servicios y eventos sin frameworks, ORM, HTTP o SDKs. Application coordina casos de uso y depende de contratos del núcleo, nunca de Infrastructure/UI ni de servicios globales del framework.
- Ubicar los puertos donde el núcleo los necesita: repositorios y capacidades del dominio en Domain; reloj, transacciones, identidad, comunicación u otras necesidades de orquestación en Application cuando esa sea su responsabilidad. No forzar interfaces ajenas al problema ni permitir que un adaptador defina la política.
- UI valida formato y traduce entrada/salida. Las escrituras y decisiones de negocio pasan por Application; los modelos ORM y componentes de pantalla no las implementan. Las consultas usan contratos de lectura cuando corresponda, sin esconder reglas en closures de UI.
- Los contextos se comunican con contratos publicados, no mediante tablas, modelos, casos de uso internos o implementaciones de otro contexto. La orquestación usa esos contratos y adaptadores explícitos. SharedKernel se limita a conceptos realmente compartidos con propietario y semántica común.
- Precisar límites transaccionales y concurrentes desde las invariantes. No considerar atómica una llamada externa junto a una transacción local. Elegir idempotencia, outbox, reintentos o compensación según el recorrido; no imponer CQRS, event sourcing o un bus por defecto.

Registrar decisiones materiales con la plantilla de `docs/plantillas/decision.md`. Mantener las incógnitas como decisiones pendientes y avanzar en lo independiente. Conservar fuentes y autorizaciones existentes; los ejemplos de otro proyecto no son requisitos.

## Aplicar a trabajo autorizado

Leer la tarea y sus criterios antes de modificar código; actualizar estado y evidencia mediante las skills del plan. El arranque del kit prepara documentación, skills, herramientas y la raíz src con `.gitkeep`, sin contextos ficticios. Instalar un framework o desarrollar funcionalidades requiere que el pedido cubra ese trabajo; no nace de invocar esta skill.

Modelar invariantes en el dominio; comprobar permisos e identidad/tenant en límites y casos de uso con contratos confiables. Tratar errores externos mediante contratos de error del núcleo; el adaptador los traduce. Registrar datos necesarios sin secretos ni cuerpos completos con información personal. La UI ofrece errores comprensibles y conserva los detalles técnicos en la observabilidad apropiada.

Probar invariantes, casos de uso, contratos y adaptadores afectados. Agregar pruebas de concurrencia, idempotencia, autorización, migración y UI cuando sus criterios las requieran. Un chequeo de imports es estructural; no demuestra aislamiento, reglas correctas ni una integración operativa.

En un repositorio existente, identificar los desvíos concretos y proponer cortes de refactorización con alcance. Una ubicación heredada fuera de src se registra como desvío, no como excepción confirmada; no agregar contextos de negocio allí ni migrar toda la aplicación sin un pedido que lo cubra. No atribuir pureza de dominio a una estructura de carpetas: revisar imports, herencia, tipos y dependencias de Domain/Application, y adaptar autoload/composición para cargar src.
