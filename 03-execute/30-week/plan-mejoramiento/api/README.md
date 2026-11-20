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
