# Perfil Go

Aplicar cuando Go esté confirmado. Revisar `go.mod`, `go.sum`, paquetes y convenciones. Router HTTP, base de datos, migrador y mensajería se eligen con el proyecto; no asumir un framework. La estructura siguiente adapta hexagonal y DDD a un servicio Go; para una biblioteca pública, definir sus paquetes exportables según el contrato y registrar esa adaptación.

## Organización por bounded context

Usar `internal/` para la lógica del servidor y `cmd/<aplicacion>/main.go` para iniciar cada ejecutable. Es la base recomendada por la [guía oficial de organización de módulos](https://go.dev/doc/modules/layout#server-project). Dentro de cada contexto, esta propuesta separa núcleo y adaptadores:

```text
go.mod
cmd/
  api/main.go
internal/
  bootstrap/
  ventas/
    domain/
    application/
    adapters/
      http/
      postgres/
  inventario/
    domain/
    application/
    adapters/
```

El instalador prepara solo `internal/.gitkeep` y `cmd/.gitkeep`; los nombres de contextos del ejemplo no se generan. Una raíz distinta elegida por el usuario se registra con solicitud y fecha. Conservar instalaciones heredadas bajo `src/` hasta una conciliación autorizada, sin mover código automáticamente.

- `domain` protege conceptos e invariantes; `application` coordina casos de uso; `adapters` implementa entradas HTTP/mensajería y salidas de persistencia/clientes. `bootstrap` conecta dependencias y `main` inicia el proceso, sin reglas de negocio.
- Cada directorio con código Go es un paquete: crear divisiones por responsabilidades concretas y evitar ciclos de imports. Mantener paquetes cohesionados, nombres en minúsculas e interfaces pequeñas definidas donde se consumen, según las necesidades del núcleo.
- `internal/` en la raíz restringe el acceso desde fuera de su árbol permitido, pero no impide imports entre contextos hermanos. Verificar esos límites mediante revisión o controles de imports; los contextos se comunican por contratos publicados. Cuando se necesite una restricción adicional del compilador, se pueden introducir directorios `internal` dentro de un contexto y exponer una fachada deliberada.

## Dependencias y verificación

- Domain no importa `net/http`, drivers SQL, frameworks o SDKs. Application usa contratos pequeños definidos según sus necesidades. No acoplar entidades a tags de un ORM o detalles de serialización de transporte.
- Handlers y consumidores convierten entrada a comandos y resultados a respuestas. Los repositorios, clientes y unidades transaccionales concretos viven en Infrastructure; main/composición los conecta.
- Decidir explícitamente dónde propagar cancelación y deadlines. `context.Context` puede ser parte de contratos de orquestación cuando se acuerde, sin convertirlo en una bolsa de dependencias o datos de negocio.
- Errores de negocio tipados o identificables desde el núcleo; traducción a códigos HTTP/gRPC en el adaptador. La identidad confiable y la autorización se verifican en los límites pertinentes.
- Mantener pruebas `*_test.go` junto al código. Usar `go test ./...` y pruebas del motor/adaptadores afectados. `go test -race ./...`, si es ejecutable en el entorno, detecta carreras de memoria y no sustituye pruebas de concurrencia transaccional. El arranque del kit no crea servicios ni agrega dependencias del producto.
