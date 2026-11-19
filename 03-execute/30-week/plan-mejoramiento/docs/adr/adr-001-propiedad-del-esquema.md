# ADR-001 · El esquema es propiedad de las migraciones de Laravel

- **Estado:** aceptada
- **Contexto:** `infra` levanta un PostgreSQL. Alguien podría poner el DDL en un script de arranque del contenedor
  (`docker-entrypoint-initdb.d`) y otro en las migraciones de Laravel; con dos dueños, nadie sabe qué esquema corre en
  producción.
- **Decisión:** **todo el DDL vive en `api/database/migrations`** (esquema `archive`, tablas, índices, restricciones
  `CHECK` y la semilla de las cinco categorías). `infra` solo crea el motor vacío, el usuario y el volumen. Lo que el
  constructor de esquemas de Laravel no expresa (índices parciales, `CHECK`) va con `DB::statement()` dentro de la
  **misma** migración.
- **Alternativas descartadas:** scripts SQL en `infra/db/init/` (segunda fuente de verdad); `DatabaseSeeder` para las
  categorías (se puede olvidar correrlo y no queda en el historial de migraciones).
- **Consecuencias:** un índice nuevo se crea en una migración nueva, nunca a mano. Un esquema cambiado a mano en el motor es
  un defecto, no una variante.
- **Cómo se verifica:** `grep -ri "create table" infra/` no devuelve nada; las consultas de `data-model.md` §10 coinciden
  con las migraciones.
