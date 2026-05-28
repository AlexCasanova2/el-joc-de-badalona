# El Joc de Badalona

Plugin personalizado para gestionar el concurso diario de preguntas y respuestas de **El Joc de Badalona** en WordPress.

## Descripción

El plugin muestra las preguntas diarias a usuarios autenticados, guarda sus respuestas mediante AJAX, recalcula puntuaciones, genera un ranking y ofrece vistas públicas con soluciones, histórico y perfil de usuario.

## Funcionalidades

- Publicación y visualización de preguntas diarias del CPT `pregunta`.
- Envío de respuestas sin recarga de página mediante AJAX.
- Cálculo de puntos por usuario y estadísticas por pregunta.
- Ranking global de hasta 100 usuarios.
- Perfil de usuario con puntuación, posición y porcentaje de aciertos.
- Panel de administración con opciones de configuración y mantenimiento.
- Exportación de resultados a CSV.
- Anulación y reactivación de preguntas.
- Reprocesado manual de respuestas y recálculo completo de puntuaciones.

## Dependencias

Este plugin **no registra** el CPT `pregunta`. Debe existir previamente en el proyecto, ya sea desde el tema o desde otro plugin.

También depende de **Advanced Custom Fields (ACF)** o de una capa compatible con `get_field()`. Las preguntas utilizan al menos estos campos:

- `opcio_1`
- `opcio_2`
- `opcio_3`
- `respuesta_correcta`

Campos adicionales usados por la lógica:

- `ejb_regalo`
- `_ejb_pregunta_nula` (meta de control interno)

## Funcionamiento general

1. El usuario autenticado ve las preguntas disponibles del día.
2. La respuesta se guarda por AJAX en el meta `respuestas_preguntas`.
3. El sistema recalcula puntuaciones y estadísticas mediante cron o desde el panel de administración.
4. El ranking y el perfil del usuario se construyen a partir de `puntos_totales` y del historial de respuestas.

## Shortcodes

- `[preguntas_diarias]`: muestra el formulario de preguntas del día.
- `[respuestas_anteriores]`: muestra las soluciones del día anterior.
- `[totes_les_respostes]`: muestra las preguntas de la edición actual 2026.
- `[totes_les_respostes_2025]`: muestra las preguntas de la edición 2025.
- `[detalle_pregunta]`: muestra el detalle completo de una pregunta individual.
- `[clasificacion]`: muestra el ranking global.
- `[ejb_perfil_usuari]`: muestra el perfil y estadísticas del usuario actual.

## Panel de administración

La pantalla de administración del plugin permite:

- Configurar el número de preguntas diarias.
- Configurar la hora límite de respuesta.
- Restablecer puntuaciones y respuestas guardadas.
- Exportar resultados a CSV.
- Procesar respuestas pendientes manualmente.
- Recalcular todas las puntuaciones.
- Marcar preguntas como nulas.
- Reactivar preguntas anuladas.

## Archivos principales

- `el-joc-de-badalona.php`: bootstrap del plugin, hooks de activación, admin, assets y AJAX.
- `includes/preguntas-functions.php`: lógica principal, shortcodes, ranking, perfil y procesamiento de respuestas.
- `js/ejb-ajax.js`: envío asíncrono de respuestas desde el frontend.
- `styles.css`: estilos públicos del plugin.
- `ROADMAP.md`: tareas pendientes y mejoras previstas.

## Datos y metadatos utilizados

Metadatos de usuario:

- `puntos_totales`
- `respuestas_preguntas`
- `respuestas_detalladas`

Metadatos de pregunta:

- `total_respuestas`
- `respuestas_correctas`
- `_ejb_pregunta_nula`
- `ejb_regalo`

## Notas técnicas

- El plugin solo permite responder a usuarios autenticados.
- El ranking muestra hasta 100 usuarios ordenados por puntuación.
- El cálculo de puntos no es estrictamente en tiempo real: depende del reprocesado interno, del cron diario o de la ejecución manual desde administración.
- Si falta el CPT `pregunta` o los campos ACF requeridos, el plugin no funcionará correctamente.
