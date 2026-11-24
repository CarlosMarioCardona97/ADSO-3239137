# ADR-003 · Los expedientes se dan de baja, no se borran

- **Estado:** aceptada
- **Contexto:** una línea de préstamo apunta a un expediente. Si el expediente se borra físicamente, el historial y el
  reporte quedan rotos. Además, un archivo público debe poder demostrar qué se prestó y a quién.
- **Decisión:** el expediente lleva `deleted_at`. Darlo de baja lo llena; el catálogo filtra por `deleted_at IS NULL` en
  un único lugar (el método `active()` de `DbDossierRepository`). Un expediente con préstamos abiertos **no** se da de baja
  (409 `DOSSIER_HAS_OPEN_LOANS`). La unicidad del número de radicado es un índice **parcial** sobre expedientes activos,
  así un radicado dado de baja puede volver a registrarse.
- **Alternativas descartadas:** borrado físico con cascada (destruye el histórico); una columna `active = false` (no dice
  cuándo); el `SoftDeletes` de Eloquent (mete la regla en el ORM, fuera del dominio, y agrega un comportamiento oculto en
  cada consulta).
- **Consecuencias:** `loan_item.dossier_id` lleva una FK `RESTRICT` como **barrera de última instancia**: un `DELETE`
  manual o un cambio de código futuro fallan de forma visible en vez de corromper el histórico. El archivo de la carátula es
  el único dato que sí se borra físicamente.
- **Cómo se verifica:** consultar un expediente dado de baja devuelve 404; un `DELETE` por `psql` sobre un expediente con
  líneas falla por la FK.
