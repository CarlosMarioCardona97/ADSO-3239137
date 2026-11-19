# ADR-004 · El reporte se calcula en el motor y agrupa por valores congelados

- **Estado:** aceptada
- **Contexto:** el reporte de expedientes más solicitados (HU-10) cruza `loan` y `loan_item` sobre un rango de fechas. Si
  leyera el título o la categoría del catálogo vivo, renombrar un expediente o una categoría cambiaría el reporte de un
  periodo ya cerrado.
- **Decisión:** (1) el reporte **no se guarda**: se calcula con una sola consulta agregada (`GROUP BY` en PostgreSQL) a
  través del puerto de lectura `ReportReader`. (2) Agrupa por `dossier_id, dossier_title, filing_number, category_name`
  **tal como quedaron congelados en la línea**. (3) `loan_item.category_name` **no tiene FK**, a propósito. (4) Si un
  expediente cambió de categoría dentro del rango, el reporte muestra **dos filas**: no elige un ganador.
- **Alternativas descartadas:** unir con el catálogo vivo (el reporte deja de ser estable); tomar «el valor más reciente
  del rango» (un préstamo nuevo cambiaría lo ya leído); materializar una tabla de reporte (segunda fuente de verdad);
  calcularlo en PHP con colecciones (trae todas las líneas a memoria).
- **Consecuencias:** CA-10.1 dice «una fila por expediente y nombre de categoría congelado», no «una fila por expediente».
  El reporte no incluye funcionarios ni archivistas (privacidad, Ley 1581 de 2012).
- **Cómo se verifica:** prestar un expediente, renombrarlo y pedir el reporte del mismo rango: el título del reporte es el
  viejo.
