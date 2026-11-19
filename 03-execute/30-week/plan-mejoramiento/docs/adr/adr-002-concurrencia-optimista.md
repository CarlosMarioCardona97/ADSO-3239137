# ADR-002 · Concurrencia optimista sobre las unidades del expediente

- **Estado:** aceptada
- **Contexto:** dos archivistas pueden entregar la **última unidad** del mismo expediente al mismo tiempo (RNF-2). Si ambos
  leen `available_units = 1` y ambos escriben, el sistema presta una unidad que no existe.
- **Decisión:** control de concurrencia **optimista**. El expediente lleva un testigo de versión (`dossier.version`). El
  repositorio guarda con `UPDATE … SET available_units = ?, version = version + 1 WHERE id = ? AND version = ?`; si
  actualiza **0 filas**, la versión cambió: se revierte la transacción completa y el servidor responde
  **409 `DOSSIER_NOT_AVAILABLE`**. La **última barrera** es el `CHECK` `ck_dossier_available_range`
  (`0 <= available_units <= total_units`).
- **Alternativas descartadas:** bloqueo pesimista de la fila (`SELECT … FOR UPDATE`, `lockForUpdate()` en Eloquent) en cada
  préstamo: serializa todo el archivo por un caso raro; contador en memoria o en caché: no sobrevive a más de un proceso
  PHP-FPM.
- **Consecuencias:** si el `CHECK` salta, **algo escribió por fuera del repositorio**: es un defecto, no un caso de
  negocio. Devolver dos veces la misma línea no suma dos veces, porque `returned_at` ya está lleno (CA-07.2).
- **Cómo se verifica:** `tests/Feature/Concurrency/LastUnitTest.php`: dos préstamos simultáneos sobre un expediente con
  una unidad; exactamente uno recibe 201 y el otro 409.
