# Perfil Flutter

Aplicar cuando Flutter esté confirmado. Revisar `pubspec.yaml`, `pubspec.lock`, plataformas destino y código existente. Gestor de estado, navegación, persistencia y backend se consultan; este perfil no impone Riverpod, Bloc, Firebase ni una arquitectura de servidor.

## Organización por bounded context

Usar `lib/` como raíz del código de la aplicación. La estructura siguiente es una adaptación de hexagonal y DDD para un proyecto que eligió esa arquitectura; Flutter recomienda separar UI y datos y considera la capa de dominio según complejidad, sin exigir este árbol: [recomendaciones oficiales](https://docs.flutter.dev/app-architecture/recommendations).

```text
pubspec.yaml
lib/
  main.dart
  app/
  contexts/
    ventas/
      domain/
      application/
      infrastructure/
      presentation/
    inventario/
      domain/
      application/
      infrastructure/
      presentation/
test/
integration_test/
```

`app` contiene router, tema y composición; `main.dart` inicia la aplicación. Los contextos surgen de responsabilidades y lenguaje, no de cada pantalla; su mapa puede diferir del backend. El instalador prepara solo `lib/.gitkeep`, sin contextos ficticios. Una raíz distinta elegida por el usuario se registra con solicitud y fecha. Conservar las rutas de instalaciones existentes hasta una conciliación autorizada.

## Núcleo Dart y paquetes

- Domain/Application usan Dart sin imports de Flutter, widgets, gestores de estado, clientes HTTP, bases locales o SDKs de plataforma. En un único paquete Flutter, controlar esa separación mediante análisis y revisión de imports; las carpetas no aíslan las dependencias de su `pubspec.yaml`.
- Cuando se necesite aislamiento del manifiesto, reutilización o desarrollo independiente, extraer el núcleo de un contexto a un paquete Dart, por ejemplo `packages/ventas_core/`, con `pubspec.yaml` sin dependencia de Flutter, API pública en `lib/ventas_core.dart`, implementación en `lib/src/{domain,application}/` y pruebas propias en `test/`. Los consumidores importan la API pública, no el `src` de otro paquete. Ver [estructura de paquetes Dart](https://dart.dev/tools/pub/package-layout#implementation-files).
- Gestionar múltiples paquetes con [Pub workspaces](https://dart.dev/tools/pub/workspaces), si la versión Dart confirmada lo permite, o dependencias locales [path](https://dart.dev/tools/pub/dependencies#path-packages). La extracción responde a fronteras concretas: no crear un paquete por cada capa por rutina. Definir dependencias explícitas; cada capa referencia solo contratos permitidos.

## Adaptadores, presentación y pruebas

- Widgets y controladores de presentación capturan eventos y presentan estados; llaman casos de uso. Los providers/blocs/notifiers no concentran invariantes de negocio ni se convierten en localizadores de servicios del núcleo.
- Repositorios y contratos externos se definen desde el núcleo; adaptadores HTTP/almacenamiento/mappers viven en Infrastructure. La composición construye las dependencias según la solución confirmada.
- Una app cliente no puede garantizar por sí sola autorización de servidor, cupos globales o una transacción distribuida. Especificar las invariantes que debe proteger el backend; offline, caché, reintentos y conflictos se diseñan cuando el producto los necesita.
- Probar dominio y casos; usar `dart test` en los paquetes Dart extraídos y `flutter test` para las pruebas de la aplicación, incluidos widgets. Probar adaptadores con dobles/contratos y recorridos en `integration_test/` en las plataformas acordadas. `flutter analyze` y un build prueban aspectos distintos de una aceptación funcional. El arranque del kit no ejecuta `flutter create` sin un pedido que lo incluya.
