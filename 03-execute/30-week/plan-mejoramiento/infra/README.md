# infra — Contenedores de Archive

Levanta todo el sistema con **un solo comando** (RNF-5, T-20). La base de datos arranca **vacía**: las tablas las crean
las migraciones de la API al arrancar ([ADR-001](../docs/adr/adr-001-propiedad-del-esquema.md)). Esta carpeta no contiene
ningún `CREATE TABLE`.

## Requisitos

Docker Desktop con Docker Compose v2. No se necesita PHP, Composer ni Node instalados en el equipo.

## Cómo levantarlo

```bash
cd infra
cp .env.example .env      # y cambie las claves
docker compose --env-file .env up --build
```

| Servicio | URL | Qué es |
|---|---|---|
| `db` | `localhost:5432` | PostgreSQL 16, base `archive` |
| `api` | `http://localhost:8000/api/v1/health` | API Laravel |
| `page` | `http://localhost:8088` | Sitio público estático (nginx) |

## Comprobar que la base arranca vacía

```bash
docker compose down -v                       # borra el volumen
docker compose --env-file .env up -d db
docker compose exec db psql -U archive -d archive -c "\dt archive.*"   # → no hay relaciones
```

## Apagar

```bash
docker compose down        # conserva los datos
docker compose down -v     # borra también el volumen de la base
```
