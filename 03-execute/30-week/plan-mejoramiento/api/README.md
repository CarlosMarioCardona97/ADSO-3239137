# api — Backend de Archive (PHP 8.3 + Laravel 12)

Implementa el contrato de [`../docs/api-contract.md`](../docs/api-contract.md) con arquitectura hexagonal
([`../docs/architecture.md`](../docs/architecture.md)).

| Carpeta | Capa |
|---|---|
| `app/Domain` | Reglas del negocio en PHP puro (artículo I) |
| `app/Application` | Casos de uso y puertos (artículo II) |
| `app/Infrastructure` | Adaptadores: HTTP, Eloquent, disco, seguridad |
| `app/Providers/PortBindingsServiceProvider.php` | Único punto de composición (artículo III) |
| `database/migrations` | Todo el DDL del esquema `archive` (ADR-001) |

## Ejecutar

Se levanta junto con la base desde [`../infra`](../infra): `docker compose --env-file .env up --build`.
La API queda en `http://localhost:8000/api/v1/health`.

## Pruebas (Pest)

Corren dentro del contenedor de la API, contra la base **`archive_test`** (nunca contra la de desarrollo:
`tests/TestCase.php` se detiene si la base no termina en `_test`).

```bash
cd ../infra
docker compose --env-file .env run --rm --no-deps -v "$(cd ../api && pwd):/app" --entrypoint php api vendor/bin/pest
```

| Carpeta | Qué prueba | Contra qué |
|---|---|---|
| `tests/Unit/Domain` | Invariantes del dominio (T-03 a T-06) | Nada: PHP puro |
| `tests/Unit/Application` | Casos de uso (T-07 a T-11) | *Fakes* en memoria (`tests/Fakes`) |
| `tests/Feature/Persistence` | Repositorios y restricciones del motor (T-12, T-18) | PostgreSQL real |
| `tests/Feature/Concurrency` | Última unidad: uno gana, otro 409 (T-13, RNF-2) | PostgreSQL real |
| `tests/Feature/Http` | Contrato HTTP, error único, roles, carátulas (T-14 a T-17) | API en memoria + PostgreSQL |
| `tests/Architecture` | Artículos I, II, III y IV (T-19) | `arch()` de Pest |
