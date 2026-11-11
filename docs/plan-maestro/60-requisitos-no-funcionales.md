# Requisitos no funcionales

## Propósito y fuentes

Criterios transversales exigidos por la fuente para trazabilidad, operación de depósito y control de inventario. Se verifican junto con los recorridos funcionales afectados; sus dependencias indican secuencia de comprobación, no permiso para postergar seguridad o usabilidad.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: Calidad y plataforma. Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

Confirmados: dos segundos para búsqueda histórica; cinco segundos para reflejar PP/stock entre puestos; concurrencia de todos los actores habilitados; auditoría retenida indefinidamente. Los usuarios y permisos son configurables, incluidos vendedores S/G. El corte usa tipografía de 16 px, controles grandes y captura manual; la validación industrial/lectores sigue pendiente. La auditoría implementa protección SQL y firmas encadenadas; resistir control total del servidor requiere SEC-001 externo.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [HECHO] UI-001 — Unificar la gestión en el panel Filament con navegación lateral por módulos

- **Capa:** SOLICITADO
- **Fuente:** pedido del usuario del 2026-11-04 de usar el mismo estilo Filament para todo, con panel izquierdo organizado, y continuar las RF desde esa base; DEC-2026-11-04-08
- **Contexto propietario:** UI de Acceso, ClientesAdministracion e InventarioTrazabilidad; composición del panel
- **Dependencias:** ACC-001, INV-001, RF-001, RF-002
- **Alcance:** integrar las pantallas existentes al shell, tema y componentes nativos de Filament; agrupar navegación y separar accesos a clientes, pedidos, catálogo, producción, stock, despachos, autorizaciones, auditoría, usuarios y roles. Mantener URLs previas mediante redirecciones y conservar permisos/escrituras de negocio.
- **Fuera de alcance:** cambiar reglas comerciales, incorporar dependencias o declarar completas otras RF por reorganizar su interfaz.
- **Invariantes:** sidebar izquierdo consistente, navegación por permisos, rutas directas protegidas, continuidad de formularios y núcleo independiente del framework.
- **Contratos y archivos previstos:** AdminPanelProvider, rutas y middleware; layout/componentes compartidos en src/Shared/UI, tema Vite, vistas/controladores de los contextos y pruebas HTTP/UI pertinentes; reglas y documentación del proyecto.
- **Criterios de aceptación:** todas las pantallas de gestión comparten sidebar y componentes Filament; módulos accesibles según permisos, aspecto adaptable y tema claro/oscuro coherente; formularios y datos de RF-001/RF-002/inventario siguen funcionando; enlaces previos redirigen al módulo correspondiente; suite, build y revisión visual verificadas.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-11
- **Próximo paso:** ninguno en UI-001; revisión general completada el 2026-11-11. Conservar Filament y verificar visualmente cada pantalla futura. RNF-002 conserva ergonomía/hardware industrial y RF-011 su aplicación pendiente del esquema.
- **Evidencia actual:** integración implementada sobre Filament 5.11.0 y Livewire 4.4.8, sin modificar dependencias ni reglas comerciales:
  - Dashboard Inicio propio, marca SIMON SRL y accesos por módulos; raíz redirige al panel. Sidebar nativo izquierdo, colapsable y agrupado en Comercial, Fábrica y Administración. Se retira el widget informativo del framework de la pantalla de trabajo.
  - Todas las pantallas de clientes/direcciones, usuarios/roles y operaciones comparten el layout nativo Filament. Secciones, campos, selectores, casillas, enlaces y botones usan componentes nativos mediante adaptadores Blade compartidos. Tema Vite en src/Shared/UI/Assets/filament.css; JavaScript de operaciones conserva sus selectores y actualización. Se elimina la hoja de estilos independiente anterior.
  - Accesos separados a pedidos, catálogo, producción, stock, despachos, autorizaciones y auditoría; usuarios y roles tienen pantallas propias. Cada módulo y fragmento de refresco valida permisos, además de los controles existentes en las escrituras. La navegación oculta módulos no autorizados. Se mantiene captura manual y altura de botones operativos de 48 px; la comprobación computada y ergonomía siguen pendientes en RNF-002.
  - URLs canónicas bajo /admin; los nombres de rutas se conservan. GET anteriores bajo /gestion redirigen conservando parámetros; POST/PUT usan 307 para conservar método y cuerpo hacia los controles canónicos. Las sesiones incorporan AuthenticateSession y rechazan escrituras tras cambiar la contraseña. Locale del panel en español y tema claro/oscuro nativo.
  - Regresión final: php vendor/phpunit/phpunit/phpunit aprobó 52 pruebas y 670 aserciones. Seis pruebas nuevas verifican sidebar/controles renderizados en 16 pantallas, casillas y asignaciones, separación de módulos, visibilidad por vendedor, accesos directos, refresco por sección, enlaces previos e invalidación de sesión. Se conservan RF-001, RF-002 y los recorridos de inventario. Pint, npm.cmd run build, node --check y git diff --check aprobados. migrate --pretend informa Nothing to migrate; no se cambian datos ni esquema.
  - Revisión visual incompleta: se observó el acceso nativo en español y modo oscuro en el navegador integrado. Las credenciales disponibles no permitieron acceder allí; no se restablecieron para esta revisión. Computer Use se detuvo al no poder determinar con suficiente confianza la URL actual de Chrome, por lo que no se siguió automatizando el navegador. No se acredita revisión visual del dashboard, formularios, modo móvil ni ergonomía; UI-001 conserva PARCIAL por esa verificación pendiente.
  - Cierre del 2026-11-11: node tests/Browser/pending-orders.mjs --management-ui verificó 20 pantallas, 80 combinaciones escritorio 1365×900/móvil 390×844 y claro/oscuro, menú nativo expandido en escritorio/adaptable en móvil, ausencia de desbordamiento y formularios/componentes renderizados. Incluye inicio, usuarios/roles, clientes y direcciones (alta/consulta/edición), pedidos/detalle, pendientes, catálogo, producción, stock, despachos, autorizaciones, auditoría, trazabilidad, picking e insumos. Búsqueda y redirección /gestion verificadas en navegador. Informe storage/logs/ui001-browser.json y capturas ui001-*.png en storage/app/private; se revisaron muestras de dashboard, cliente, dirección, usuarios, pedidos, depósito y picking. Fixture con 20 notas/100 líneas y usuarios sintéticos, SQLite/perfil temporales eliminados al terminar; sin cambios en cuentas reales.
  - Pruebas pertinentes finales: FilamentManagementTest, CustomerProfilesTest, CustomerAddressesTest y PickingTest, 44 pruebas/635 aserciones aprobadas; seis pruebas JavaScript de refresco aprobadas. La regresión general vigente tiene 155 pruebas/1644 aserciones aprobadas; build y Pint aprobados. Los formularios operativos nuevos utilizan la clase compartida con botones de 48 px. Se conserva la evidencia de la revisión histórica incompleta, resuelta por esta verificación; no se atribuye aceptación industrial ni despliegue de RF-011 a estas capturas.

### [HECHO] UI-002 — Incorporar Livewire en todas las interfaces de gestión donde corresponda

- **Capa:** SOLICITADO
- **Fuente:** solicitud del usuario del 2026-11-11 de incorporar Livewire en todos los sectores donde se permita y necesite, y realizar esta tarea a continuación; reiteración del mismo día para completarla y establecer su uso como regla permanente.
- **Contexto propietario:** UI de todos los contextos y composición del panel Filament.
- **Dependencias:** UI-001
- **Alcance:** revisar de forma exhaustiva las pantallas y recorridos de gestión existentes e incorporar Livewire o componentes reactivos nativos de Filament en todos los lugares donde su uso sea técnicamente viable y necesario; incluye formularios, búsquedas, filtros, tablas, paginación, actualizaciones, selección de direcciones y líneas, y picking cuando corresponda. Registrar por sector qué se adaptó, qué ya lo utiliza y dónde no corresponde, con motivo concreto. Aplicar el mismo criterio a las interfaces futuras al implementar cada tarea autorizada.
- **Fuera de alcance:** modificar reglas de negocio, incorporar funcionalidades comerciales nuevas, cambiar dependencias o migrar datos y esquema sin autorización; usar Livewire en capas de dominio y aplicación o en partes que no lo permitan o necesiten.
- **Invariantes:** conservar Filament, navegación y diseño adaptable; validar autorización en el servidor en cada lectura y acción, incluso en solicitudes posteriores de Livewire; delegar escrituras en casos de uso, respetar contratos entre contextos, datos y operación existente, prevenir duplicaciones y mantener las garantías de concurrencia y auditoría.
- **Contratos y archivos previstos:** componentes y vistas bajo UI de los contextos, composición del panel, rutas y activos existentes cuando corresponda; casos de uso y contratos existentes; pruebas funcionales, de seguridad y de navegador pertinentes.
- **Criterios de aceptación:** inventario de todos los sectores y recorridos existentes con resultado y justificación de los puntos donde no corresponda Livewire; todos los puntos viables y necesarios incorporados y comprobados; formularios, navegación, errores, filtros y estados conservados; permisos y revocaciones verificados en cada acción; operaciones sin duplicados y sin pérdida de datos; actualización de saldos y límites de tiempo existentes preservados; pruebas pertinentes, build y verificación visual y adaptable según lo afectado. La validación documental por sí sola no acredita aceptación funcional.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-11
- **Última actualización:** 2026-11-11
- **Próximo paso:** ninguno en UI-002; tarea cerrada. Aplicar la regla permanente de AGENTS.md a toda interacción futura técnicamente viable y conservar sus verificaciones de permisos, casos de uso y presentación.
- **Evidencia actual:** Livewire 4.4.8 y Filament 5.11.0 están instalados; Inicio usa una página Filament basada en Livewire. La revisión inicial observó principalmente controladores, Blade, formularios HTTP y JavaScript propios. Avance del 2026-11-11:
  - Clientes: búsqueda, limpieza y paginación reactivas, con filtro en URL. Pedidos: agregar y quitar líneas y cambiar cliente/dirección con Livewire, preservando datos, foco, validaciones y envío existente por caso de uso. Picking: filtros, paginación, lectura y retiro reactivos; el despacho conserva el flujo de escritura existente. En cada componente se comprueban permisos y usuario activo; se agregaron middleware persistentes para sesión, permisos y disponibilidad de picking en actualizaciones Livewire.
  - Suite PHP completa: 186 pruebas y 1776 aserciones aprobadas. Seis pruebas JavaScript de refresco aprobadas; build Vite y Pint aprobados. Ensayos Edge con SQLite temporal: búsqueda de clientes y URL, líneas y direcciones de pedidos, y lectura, retiro y despacho de picking; se comprobó ausencia de desbordamiento móvil y se revisaron capturas de picking escritorio/móvil en storage/app/private/ui002-picking-*.png. Informes sintéticos en storage/logs/ui002-customers-browser.json, ui002-orders-browser.json y ui002-picking-browser.json. No se utilizó hardware físico.
  - Estado histórico del primer avance: UI-002 permaneció PARCIAL por los sectores y recorridos todavía sin revisar o adaptar, incluidos formularios y filtros HTTP/JavaScript, y sus verificaciones integrales. Ese avance por sí solo no acreditaba adopción general ni aceptación completa.
  - Cierre del 2026-11-11: navegación SPA nativa de Filament y acciones Livewire en todos los formularios de gestión existentes. ManagementActions admite una lista explícita de acciones propias y reutiliza sus controladores, FormRequest, validaciones, permisos y casos de uso; normaliza entradas, conserva los campos tras rechazos, evita doble envío mientras la solicitud está pendiente y conserva la confirmación durante la navegación. No agrega reglas comerciales ni dependencias. Las solicitudes de lectura de LiveBalances están aisladas de las escrituras: polling nativo cada dos segundos, cancelación a los 2500 ms, aviso de datos desactualizados y retirada de saldos ante pérdida de acceso. Los formularios quedan fuera del fragmento actualizado. Se retiró el fetch propio de saldos y direcciones.
  - Inventario completo: Inicio, ingreso y navegación ya utilizan componentes Filament/Livewire; el panel activa SPA nativa. Clientes conserva búsqueda/paginación específicas y ahora tramita altas, edición, observaciones crediticias y direcciones mediante acciones Livewire. Pedidos conserva los editores reactivos y tramita registro, borradores, edición, confirmación y anulación mediante Livewire; filtros, consultas y enlaces utilizan navegación Livewire. Pendientes, Producción y Stock refrescan sus informes con LiveBalances, y sus filtros/paginación conservan parámetros mediante navegación Livewire. Catálogo, tandas, altas de terminados, stock inicial, autorizaciones y despachos utilizan acciones Livewire; las direcciones dependientes del despacho también se consultan por Livewire. Picking combina sus filtros/lecturas/retiros específicos con acciones Livewire para identificación y despacho. Insumos/vencimientos, cuenta corriente, usuarios y roles utilizan acciones Livewire y navegación para consultas/páginas. Trazabilidad y auditoría conservan sus datos históricos y consultas, con filtros y navegación Livewire.
  - Aplicabilidad: las tablas, textos, etiquetas, temas y secciones estáticas se presentan dentro de esos recorridos; no necesitan una solicitud adicional por celda o texto. Los estados visuales locales de Filament mantienen sus componentes nativos. Las rutas HTTP se conservan para compatibilidad y acceso sin JavaScript; el uso normal de la UI emplea Livewire. Consola, migraciones y archivos estáticos no son interacciones del panel. Los módulos de negocio aún no implementados conservan sus tareas y quedan sujetos a la regla permanente registrada en AGENTS.md; no se amplía su alcance por este cierre.
  - Verificación final: suite PHP completa, 191 pruebas y 1806 aserciones aprobadas, incluidas validación, permisos, reintentos, parámetros de rutas, edición de direcciones y cambios de estado mediante Livewire. Cinco pruebas JavaScript verifican cancelación de lecturas lentas, retirada de datos en 401/403/419 o redirección, errores de red/servidor y recuperación. Build Vite, Pint y sintaxis JavaScript aprobados. UI-001 permanece HECHO; se repitieron 20 pantallas en 80 combinaciones escritorio/móvil y claro/oscuro sin desbordamiento y se revisaron capturas finales de pedidos, producción, perfil y cuenta corriente.
  - Ensayos Edge con SQLite/perfiles temporales: doce recorridos de formularios administrativos/operativos, rechazo de CUIT duplicado conservando campos, movimientos financieros, editores de pedidos, picking, trazabilidad y navegación. La medición con cinco actores, mil notas y cinco mil líneas produjo 30 observaciones: máximo 2154,813 ms frente al límite de 5000 ms. Se verificaron formulario/foco conservados y retirada de datos tras revocar permisos. Informes en storage/logs/ui002-forms-browser.json, ui002-finance-browser.json, ui002-orders-browser.json, ui002-customers-browser.json, ui002-picking-browser.json, ui002-traceability-browser.json, ui002-management-browser.json, ui002-balances-browser.json y ui002-production-browser.json; capturas ui002-*.png en storage/app/private. Recursos temporales eliminados al terminar; no se modificaron datos ni esquema de la base compartida. Esta evidencia no sustituye las pruebas industriales, MySQL productivo ni los pendientes de otras RF/RNF.

### [PENDIENTE] RNF-001 — Responder búsquedas históricas de lotes y partidas en un máximo de 2 segundos

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RNF 01; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Calidad y plataforma
- **Dependencias:** RF-009
- **Alcance:** Búsqueda histórica que vincula clientes con lotes/partidas, desde el ingreso del parámetro hasta el resultado visible, en un máximo de 2 segundos.
- **Fuera de alcance:** Afirmar cumplimiento a partir de un benchmark vacío o de una consulta SQL aislada.
- **Invariantes:** El máximo es 2 segundos, no una media o un percentil sustituto; medir el recorrido definido y conservar exactitud y permisos.
- **Contratos y archivos previstos:** Propuesta: consultas/índices de trazabilidad y pruebas de rendimiento sobre datos representativos; protocolo y evidencia del entorno de medición.
- **Criterios de aceptación:** Cada consulta del conjunto acordado devuelve resultados correctos y visibles en no más de 2 segundos; se incluyen coincidencias, ausencia de resultados y el historial definido. La evidencia registra volumen, hardware, motor, concurrencia, caché y comienzo/final de la medición.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** al implementar el buscador histórico, medir máximo de dos segundos con todos los actores habilitados y un volumen representativo; las exportaciones reales se harán después
- **Evidencia actual:** Requisito RNF 01 incorporado desde la fuente conservada; sin implementación ni pruebas funcionales específicas observadas. Dependencias, contratos y casos de prueba son descomposición técnica del plan, no decisiones comerciales adicionales.

### [PARCIAL] RNF-002 — Ofrecer interfaz de depósito legible, de alto contraste y operable con lectores

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RNF 02; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Calidad y plataforma
- **Dependencias:** INV-001, RF-011
- **Alcance:** Diseño de depósito con alto contraste, tipografía clara de al menos 14 px, campos legibles, botones grandes e integración ágil con códigos de barras/QR.
- **Fuera de alcance:** Declarar accesibilidad certificada o adoptar umbrales de botones/contraste que no fueron acordados.
- **Invariantes:** El tamaño mínimo exigido es 14 px; mensajes de validación, lotes, partidas y cantidades también deben poder leerse. El lector no debe inducir transcripciones manuales innecesarias.
- **Contratos y archivos previstos:** Propuesta: pantallas/componentes de depósito y adaptador de lectura; comprobaciones de estilos, foco y prueba operativa con el equipo acordado.
- **Criterios de aceptación:** Se verifica tamaño computado mínimo de 14 px en textos operativos y contraste/campos/botones según medidas acordadas; un recorrido de picking con lector funciona y mantiene el foco previsto. Se evalúan códigos largos, errores de lectura y uso en el entorno industrial con representantes del depósito.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** validar operación industrial con representantes y ampliar la verificación a las futuras pantallas/lectores
- **Evidencia actual:** La interfaz inicial utilizaba base 16 px y controles de 48 px. UI-001 integra ahora operaciones en el tema y componentes nativos de Filament, manteniendo campos de lote/fabricación, captura manual y botones operativos de 48 px. HTML/build y regresión aprobados; tamaño computado de textos, contraste real, revisión visual completa, ergonomía con Claudia y hardware siguen pendientes. No se acredita ese cumplimiento por adoptar el tema.

### [PARCIAL] RNF-003 — Aplicar roles individuales y auditoría no modificable en cada alta y baja de stock

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RNF 03; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Calidad y plataforma
- **Dependencias:** ACC-001, INV-001, SEC-001
- **Alcance:** Gestión configurable de usuarios/roles/permisos, incluidos vendedores S/G; log obligatorio no modificable de altas/bajas con retención indefinida y protección frente a administradores según la arquitectura delegada.
- **Fuera de alcance:** Permisos ligados al nombre, cuentas compartidas o credenciales personales inventadas; afirmar resistencia total al administrador del servidor sin la capa externa SEC-001.
- **Invariantes:** Toda alta/baja registra fecha, hora, identidad del usuario y cantidad de pares; un rechazo de permisos no altera stock. La operación y su registro deben mantener consistencia y el historial no permite edición/borrado mediante los accesos cubiertos por el modelo de protección acordado.
- **Contratos y archivos previstos:** Propuesta: contratos de identidad/autorización en el núcleo y auditoría de movimientos; adaptadores de autenticación, persistencia y protección del historial; pruebas por rol.
- **Criterios de aceptación:** Las pruebas de cada rol permiten acciones asignadas y rechazan las restantes tanto por UI como acceso directo; cada alta/baja válida deja los cuatro datos obligatorios. Se rechazan modificación/borrado del historial y operaciones sin usuario; un fallo de auditoría no permite un cambio de stock definitivo sin registro. Se prueba la protección privilegiada conforme al modelo de amenaza definido.
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** desplegar y verificar SEC-001; mantener controles de autorización y auditoría en cada recorrido futuro
- **Evidencia actual:** Se implementan roles configurables, denegación en servidor, bloqueo de quitar el último administrador, movimientos+auditoría atómicos, tablas append-only, HMAC encadenado y verificador. Las pruebas detectan manipulación aun eliminando un disparador. Sin copia/cierre externo no se acredita resistencia a truncamiento/restauración por control total del servidor; por eso permanece PARCIAL.


### [PENDIENTE] SEC-001 — Desplegar evidencia externa inmutable y recuperación de auditoría

- **Capa:** SOLICITADO
- **Fuente:** SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Seguridad e infraestructura
- **Dependencias:** INV-001
- **Alcance:** Copias/cierres firmados fuera del servidor de fábrica, almacenamiento inmutable y credenciales separadas; retención indefinida y recuperación verificable.
- **Fuera de alcance:** Ampliar el trabajo a módulos pospuestos o atribuir aceptación operativa a pruebas locales.
- **Invariantes:** Conservar fuentes, permisos configurables y evidencia; no confundir saldos físicos con dinero ni borrar historia.
- **Contratos y archivos previstos:** Contratos/casos de uso del contexto, adaptadores y pruebas pertinentes; documentación del mismo plan.
- **Criterios de aceptación:** Un administrador del servidor no puede destruir también la evidencia externa; un rollback/truncamiento se detecta contrastando el último cierre conservado. Verificar restauración y protección/custodia de claves históricas. No declarar esta infraestructura operativa sin una prueba real.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** definir y desplegar el destino externo y las credenciales separadas cuando se prepare la operación; las firmas locales ya sirven como base
- **Evidencia actual:** Trabajo registrado a partir de SRS-ACL-2026-11-04; sin implementación de este módulo todavía.
