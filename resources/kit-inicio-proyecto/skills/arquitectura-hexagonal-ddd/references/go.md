# Perfil Go

Aplicar cuando Go esté confirmado. Revisar `go.mod`, `go.sum`, paquetes y convenciones. Router HTTP, base de datos, migrador y mensajería se eligen con el proyecto; no asumir un framework.

- Raíz obligatoria `src/` desde la raíz del repositorio, salvo elección expresa del usuario: `src/<contexto>/{domain,application,infrastructure,transport}`. Adaptar imports y, si hacen falta restricciones de visibilidad, introducir internal dentro de src; no reemplazar la raíz por `internal/<contexto>`. `cmd/<aplicacion>` puede contener solo arranque/composición. Mantener paquetes cohesionados sin replicar una jerarquía PHP literalmente. La convención Go es una referencia para el empaquetado, no una excepción automática a la raíz acordada: [organización de módulos](https://go.dev/doc/modules/layout).
- Domain no importa `net/http`, drivers SQL, frameworks o SDKs. Application usa contratos pequeños definidos según sus necesidades. No acoplar entidades a tags de un ORM o detalles de serialización de transporte.
- Handlers y consumidores convierten entrada a comandos y resultados a respuestas. Los repositorios, clientes y unidades transaccionales concretos viven en Infrastructure; main/composición los conecta.
- Decidir explícitamente dónde propagar cancelación y deadlines. `context.Context` puede ser parte de contratos de orquestación cuando se acuerde, sin convertirlo en una bolsa de dependencias o datos de negocio.
- Errores de negocio tipados o identificables desde el núcleo; traducción a códigos HTTP/gRPC en el adaptador. La identidad confiable y la autorización se verifican en los límites pertinentes.
- Usar `go test ./...` y pruebas del motor/adaptadores afectados. `go test -race ./...`, si es ejecutable en el entorno, detecta carreras de memoria y no sustituye pruebas de concurrencia transaccional. El arranque del kit no crea servicios ni agrega dependencias del producto.
