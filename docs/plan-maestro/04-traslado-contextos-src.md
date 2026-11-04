# Traslado de contextos a src

Fuente técnica fijada al [commit 04f621f12421e819bfeb1cc1518c4e864617d14e](https://github.com/wj-004/Promt-AI/blob/04f621f12421e819bfeb1cc1518c4e864617d14e/kit-inicio-proyecto/skills/arquitectura-hexagonal-ddd/references/laravel.md). La raíz requerida es src y Composer debe adaptarse a ella.

## Tareas

### [HECHO] REF-001 — Trasladar contextos a src y verificar la integración Laravel

- **Capa:** SOLICITADO
- **Fuente:** pedido del usuario del 2026-11-04; Promt-AI commit 04f621f12421e819bfeb1cc1518c4e864617d14e; DEC-2026-11-04-06
- **Contexto propietario:** arquitectura, composición Laravel y contextos existentes
- **Dependencias:** INI-002
- **Alcance:** mover contextos, vistas y activos propios a src, configurar prefijo Simon, actualizar imports/modelo/factories/auth/comandos, registrar migraciones y vistas con sus namespaces y sincronizar skills/kit/documentación/plan
- **Fuera de alcance:** cambiar reglas comerciales, dependencias, identidades de migración o datos del sistema; crear excepciones no solicitadas a src
- **Invariantes:** raíz propia src, núcleo independiente de Laravel, clases sin duplicados/aliases heredados, migración instalada reconocida por el mismo nombre y fuentes/evidencia conservadas
- **Contratos y archivos previstos:** src; composer.json; AppServiceProvider; auth, rutas, factories, seeder y pruebas; phpunit.xml; entradas Vite; skills locales y kit reusable; arquitectura, fuentes, decisiones y visor
- **Criterios de aceptación:** Composer válido y PSR-4 estricto sin errores; auth/factory/CLI/rutas resuelven nuevos namespaces; migración descubierta desde src sin reaplicar DDL; regresión de operaciones aprobada; kit portable probado; plan válido y visor regenerado
- **Responsable:** Codex
- **Fecha de inicio:** 2026-11-04
- **Última actualización:** 2026-11-04
- **Próximo paso:** mantener todo desarrollo propio en src; continuar las tareas de negocio pendientes con sus autorizaciones y fuentes
- **Evidencia actual:** traslado completo a src, incluidas vistas y activos propios. Composer declara Simon y valida PSR-4 estricto sin cambiar dependencias. Auth apunta a Simon\Acceso\Infrastructure\Persistence\User; factory explícita, comandos, rutas y vistas por namespace resuelven sus adaptadores.
  - Regresión Laravel: 17 pruebas y 97 aserciones aprobadas, incluidos modelo/factory, descubrimiento de la migración desde src y preservación de su identidad. Pint y build Vite aprobados; JavaScript verificado.
  - Base instalada: la migración histórica conserva el mismo nombre y estado aplicado; migrate --pretend informa Nothing to migrate. No se ejecutó una segunda creación de tablas ni se renombraron datos.
  - Kit actualizado 1.1.0: 45 casos, 41 aprobados y cuatro omitidos por WinError 1314 al crear enlaces simbólicos. Se conservan correcciones Windows de puerto exclusivo, URLs, UTF-8 y tiempos máximos.
  - Skills locales: 17 recursos coinciden con el kit. Metadatos source_root=src, source_root_override=false y prefijo Simon; procedencia e historial conservados.
  - ZIP portable: 33 recursos y manifiesto SHA-256 comprobado. Fuentes conocidas indexadas sin reabrir preguntas ya respondidas ni postergaciones expresas.
  - Plan maestro validado y visor regenerado; la evidencia acredita el traslado y la regresión local. Los límites previos de concurrencia/volumen real, ergonomía y copia externa de auditoría conservan sus tareas.
