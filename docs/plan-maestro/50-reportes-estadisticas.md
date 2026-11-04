# Reportes y estadísticas

## Propósito y fuentes

Consultar proyecciones de ventas, producción e inventario de sus contextos propietarios. Este contexto no modifica operaciones de negocio ni presume que los importes, costos o estados de planta ya existen.

Fuente SRS-2026-11-04: [texto original suministrado por el usuario](fuentes/2026-11-04-requisitos-originales.txt). Correspondencias y dudas en [requisitos y alcance](02-requisitos-y-alcance.md). La fuente inicial incorporó requisitos al plan; las aclaraciones posteriores autorizan INV-001 y su gestión de permisos ACC-001. Los módulos pospuestos conservan su alcance.

## Lenguaje y fronteras

Contexto propietario: Analitica (frontera propuesta). Los contratos del corte autorizado se fijan en INI-002 y la arquitectura; los restantes se precisan al iniciar cada módulo futuro. Se conserva el stack existente; no se crean carpetas ni dependencias con esta documentación.

## Reglas, decisiones pendientes y estado observado

El usuario pospuso expresamente curva, rubro, rentabilidad, obsolescencia y rotación. RF-016/RF-017 siguen PENDIENTE, sin excluirse del alcance y sin exigir estas definiciones para cerrar el primer corte de arquitectura/inventario.

El estado inicial era un esqueleto sin procesos comerciales. El corte INV-001 incorpora las operaciones descritas en [las aclaraciones](03-decisiones-relevamiento.md); las tareas conservan el trabajo que aún falta.

## Tareas

### [PENDIENTE] RF-016 — Generar tablero e informes de ventas por curva, planta y rentabilidad por rubro

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF16; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Analitica (frontera propuesta)
- **Dependencias:** RF-010, RF-014, RF-015
- **Alcance:** Informes automáticos de desempeño de ventas por curva, estados de producción (Informe de Planta) y rentabilidad por rubro.
- **Fuera de alcance:** Inferir márgenes, costos o reglas contables ni fijar reportes por correo sin solicitud.
- **Invariantes:** Cada indicador conserva definición, unidad, período y procedencia; un costo o estado no disponible no se presenta como rentabilidad o producción cero.
- **Contratos y archivos previstos:** Propuesta: contratos de lectura de ventas, producción y costos; proyecciones/tablero en Analitica y mecanismo de actualización automática por definir.
- **Criterios de aceptación:** Un conjunto conocido de ventas, producción e importes genera los tres informes y sus totales coinciden con las fórmulas acordadas; se comprueban devoluciones/entregas parciales según su definición contable. Se verifica actualización automática y restricción de acceso a datos financieros.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** definiciones pospuestas expresamente por el usuario; retomar al iniciar reportes, sin bloquear el corte actual
- **Evidencia actual:** Se conserva el requisito original y sus criterios como planificación futura. El usuario indicó que curva/rubro/rentabilidad/obsolescencia/rotación se tratarán más adelante; no se implementaron ni se infieren fórmulas.

### [PENDIENTE] RF-017 — Consultar antigüedad, obsolescencia, rotación por variante y distribución geográfica

- **Capa:** SOLICITADO
- **Fuente:** SRS-2026-11-04, RF17; aclaraciones posteriores SRS-ACL-2026-11-04; DEC-2026-11-04-05
- **Contexto propietario:** Analitica (frontera propuesta)
- **Dependencias:** RF-002, RF-012, RF-014, RF-015
- **Alcance:** Estadísticas de antigüedad y obsolescencia del stock, rotación por talle/color y distribución geográfica de pedidos.
- **Fuera de alcance:** Aplicar depreciación contable o clasificar stock como obsoleto con umbrales supuestos.
- **Invariantes:** Las métricas usan fechas, cantidades, variantes y destinos trazables; las categorías de obsolescencia y el denominador/período de rotación se acuerdan, no se inventan.
- **Contratos y archivos previstos:** Propuesta: contratos de lectura de movimientos, existencias, pedidos, destinos y devoluciones; proyecciones/consultas en Analitica.
- **Criterios de aceptación:** Datos de prueba con edades, talles/colores y destinos conocidos producen los cuatro grupos de estadísticas; los resultados concilian con sus fuentes y muestran período y fórmula. Se prueban inventario sin movimientos y devoluciones para evitar divisiones por cero o conteos dobles.
- **Responsable:** sin asignar
- **Fecha de inicio:** pendiente
- **Última actualización:** 2026-11-04
- **Próximo paso:** definiciones pospuestas expresamente por el usuario; retomar al iniciar reportes, sin bloquear el corte actual
- **Evidencia actual:** Se conserva el requisito original y sus criterios como planificación futura. El usuario indicó que curva/rubro/rentabilidad/obsolescencia/rotación se tratarán más adelante; no se implementaron ni se infieren fórmulas.
