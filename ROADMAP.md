# Roadmap: Mejoras El Joc de Badalona

Este documento detalla los pasos para corregir errores y mejorar el plugin.

## 1. Correcciones Críticas
- [x] **1.1 Datos Legacy:** No ignorar respuestas antiguas sin estado en el recálculo.
- [x] **1.2 Preguntas Nulas:** Asegurar que las preguntas anuladas resten puntos en el recálculo.
- [x] **1.3 Detalle AJAX:** Guardar `respuestas_detalladas` también en envíos por AJAX.

## 2. Optimización y UX
- [x] **2.1 Sábados:** Permitir ejecución del cron los sábados para procesar el viernes.
- [x] **2.2 Invitados:** Ocultar formulario de preguntas a usuarios no registrados.
- [x] **2.3 Sincronización Ranking:** Unificar lógica de ordenación (PHP vs AJAX).

## 3. Refactorización
- [ ] **3.1 Hardcoding:** Reemplazar el ID 1525 por un campo ACF dinámico.
- [ ] **3.2 CSV:** Corregir codificación para Excel.
