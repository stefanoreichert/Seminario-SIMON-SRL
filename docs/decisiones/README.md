# Decisiones de Seminario SIMON SRL

## DEC-2026-11-04-01 — Integrar arquitectura hexagonal y DDD

- Fecha y estado: 2026-11-04, adoptada para la preparación y trabajo posterior autorizado.
- Fuente: solicitud del usuario de implementar el prompt maestro y kit según wj-004/Promt-AI.
- Decisión: mantener tres skills locales, un único esquema versión 1 y un plan en Markdown compartido por validador, reportes y visor.
- Alternativas: mantener solo el esqueleto actual; generar herramientas diferentes. Se reutiliza el kit solicitado para conservar compatibilidad.
- Alcance: herramientas y documentación; las funcionalidades se desarrollan con requisitos y pedidos concretos.

## DEC-2026-11-04-02 — Adaptar al stack y estructura existentes

Ubicación histórica bajo app sustituida por DEC-2026-11-04-06. Se conservan las decisiones de stack y la evidencia previa.

- Fecha y estado: 2026-11-04, adoptada como adaptación de la instalación.
- Fuente: `composer show --direct`, `php --version`, `package.json`, rutas, configuración y convenciones actuales; reglas de `AGENTS.md`.
- Decisión: perfil Laravel, PHP 8.4, Laravel 13, Filament 5 y Livewire 4 observados. Futuras capas por contexto bajo `app` y autoload `App\`.
- Alternativas: perfil neutral u otra raíz de código. Se conserva el stack existente y se consulta cualquier cambio material futuro.
- Alcance: conciliar `AGENTS.md` agregando enlaces y principios, sin reemplazar las reglas Laravel; kit reutilizable en `resources/kit-inicio-proyecto`.
- Consecuencias: no se cambian dependencias, rutas de producto, autoload ni datos. El despliegue y el motor de producción quedan por definir.

## DEC-2026-11-04-03 — Mantener pendientes las definiciones de negocio

- Fecha y estado: 2026-11-04, decisión expresa del usuario; la postergación general queda actualizada por DEC-2026-11-04-04 al recibir los requisitos.
- Fuente: respuesta «si, dejalo pendiente» a la consulta sobre problema, actores y procesos.
- Decisión: registrar preguntas abiertas y próximos pasos en la ficha y el plan. No inventar requisitos, módulos, fechas ni criterios comerciales.
- Alternativas: relevar ahora esos requisitos. Se difiere esa definición y se completa la preparación independiente.
- Alcance: INI-001, INI-002 e INI-003 conservan el trabajo que falta. No constituye exclusión de funcionalidad ni impedimento para usar el kit.

## DEC-2026-11-04-04 — Incorporar los requisitos suministrados al plan maestro

- Fecha y estado: 2026-11-04, incorporación solicitada por el usuario.
- Fuente: adjunto conservado como [SRS-2026-11-04](../plan-maestro/fuentes/2026-11-04-requisitos-originales.txt) y pedido «los requerimentos del sistema son los siguientes, agregalos al plan maestro».
- Decisión: registrar íntegramente RF01–RF17 y RNF 01–RNF 03, conservar sus IDs originales en Fuente y crear tareas compatibles RF-001–RF-017 y RNF-001–RNF-003. Actualizar ficha, preparación, mapa preliminar y visor del mismo plan.
- Alternativas: conservar la postergación general o incorporar solo una síntesis. Se mantiene el texto original y una correspondencia completa para evitar pérdidas.
- Alcance: documentación y planificación. SOLICITADO indica que el requisito fue aportado para el sistema; este pedido no inicia su implementación. Las veinte tareas permanecen PENDIENTE.
- Consecuencias: DEC-2026-11-04-03 conserva su historia, pero ya hay requisitos conocidos. Los contratos, dependencias técnicas y criterios detallados son propuestas de descomposición; las preguntas DEF-01–DEF-14 registran reglas todavía no definidas. El mapa de contextos no se considera aprobado por el solo hecho de documentarlo.
- Límites: la mención de formularios Rentas, normas IRAM o seis meses legales en la fuente no acredita vigencia normativa ni certificación. Su comprobación pertinente se incluye en las tareas antes del desarrollo.

## DEC-2026-11-04-05 — Precisiones y autorización del primer flujo

- Fuente: [SRS-ACL-2026-11-04](../plan-maestro/03-decisiones-relevamiento.md), respuestas del usuario a los puntos agrupados.
- Estado y fecha: 2026-11-04, decisiones de negocio informadas y decisiones técnicas delegadas.
- Decisión: usuarios/roles/permisos configurables; Sandra/Gustavo usuarios S/G; CUIT único obligatorio; crédito y condiciones manuales de administración; número global y estados aceptados; stock físico; alta de terminados descuenta proceso; lote/fabricación e IRAM 3610; avisos de vencimiento sin bloqueo automático.
- Autorización: implementar alta de terminados y baja al confirmar despacho, con reducción del saldo de pares y deuda monetaria separada. ACC-001 cubre permisos necesarios e INV-001 el primer recorrido. No se interpreta como autorización de todos los módulos del plan.
- Postergaciones: hojas de ruta a módulo específico, reportes para después, exportaciones/importaciones cuando estén disponibles y canal Internet futuro. Las preguntas pospuestas no bloquean el corte autorizado.
- Devoluciones: seis meses desde fabricación como regla operativa aportada, aprobación por Claudia según permisos, reingreso solo si apto y nota negativa sin reemplazo. La conformidad legal se valida al trabajar esa función.
- Seguridad: retención indefinida. Se eligen permisos mínimos, transacción de movimiento/auditoría, append-only y firmas encadenadas con clave fuera de la base. SEC-001 agrega evidencia externa resistente al control total del servidor; RNF-003 no se cierra hasta verificarla.
- Alternativas: mantener un rol fijo por nombre, mezclar deuda con cantidades o bloquear el avance por reportes/exportaciones pospuestos. Se adoptan contratos y módulos separados.
- Definiciones abiertas acotadas: duplicado comercial y edición/anulación tras parciales, ejemplares impositivos, anticipación de alertas, datos/volúmenes reales y detalles de módulos futuros. Se conservan sin otra ronda de preguntas.

## DEC-2026-11-04-06 — Trasladar el código propio a src

- Fecha y estado: 2026-11-04, solicitada por el usuario e implementada en REF-001.
- Fuente: pedido expreso de consultar Promt-AI actualizado y trasladar contextos, Composer, namespaces, migraciones, skills y documentación. Referencia fijada al commit 04f621f12421e819bfeb1cc1518c4e864617d14e.
- Decisión: raíz src y prefijo propio Simon; contextos Acceso, ClientesAdministracion, InventarioTrazabilidad, VentasPedidos y soporte Shared. La composición técnica conserva App y las rutas exigidas por Laravel.
- Modelo y comandos: User se ubica en Acceso/Infrastructure/Persistence y sus factories/auth se conectan explícitamente. Los comandos de acceso/auditoría se registran desde UI/Console de sus contextos.
- UI y activos: las vistas propias usan UI/Views y namespaces de vistas registrados en la composición. CSS/JavaScript de operaciones se ubican en UI/Assets; resources conserva los puntos técnicos de entrada Vite y la bienvenida del esqueleto.
- Migraciones: mover la migración transversal histórica a Shared/Infrastructure/Database/Migrations conservando su nombre/identidad; registrar descubrimiento desde src. Las migraciones técnicas iniciales de Laravel no se reescriben.
- Alternativas: conservar contextos bajo App por su autoload heredado o duplicarlos con aliases. Se adopta el traslado solicitado y se actualizan todas las referencias activas, sin copiar clases duplicadas.
- Alcance y consecuencias: refactorización de ubicación/autoload; sin cambiar reglas de negocio, versiones de dependencias, identificadores ni datos. La evidencia de decisiones previas se conserva marcada como histórica.
- Recursos: actualizar kit 1.1.0 y skills locales, registrar source_root=src y source_root_override=false. Las fuentes adicionales/exportaciones ya pospuestas no generan otra ronda de preguntas ni cambian esta autorización.

Para decisiones posteriores usar [la plantilla](../plantillas/decision.md), conservar historial, fuente, alternativas y alcance. Las exclusiones explícitas se identifican con `DEC-AAAA-MM-DD-NN` en Fuente de las tareas afectadas.
