# tool — Herramientas de Archive

Todas hablan **solo por la API**, nunca con SQL directo (T-21). Se ejecutan desde [`../infra`](../infra), sin instalar
PHP en el equipo.

| Archivo | Para qué | Comando |
|---|---|---|
| `seed.php` | Datos de demostración: 40 expedientes, 10 funcionarios, 15 préstamos y 3 vencidos | `docker compose --env-file .env --profile seed run --rm tool` |
| `concurrency-test.php` | RNF-2: dos peticiones simultáneas por la última unidad, 10 rondas | `docker compose --env-file .env --profile seed run --rm tool /tool/concurrency-test.php` |
| `load-test.php` | RNF-1: búsqueda con 5 000 expedientes en menos de 300 ms | `docker compose --env-file .env --profile seed run --rm tool /tool/load-test.php` |
| `ApiClient.php` | Cliente HTTP compartido | — |

## Cómo se crean préstamos vencidos usando solo la API

El perfil `seed` levanta `api-past`, una segunda instancia de la API sobre la misma base con
`CLOCK_OFFSET_DAYS=-6`. Lo que se presta por ella nace hace 6 días y venció hace uno (plan D-15).

## Orden recomendado

Las pruebas de concurrencia y de carga agregan registros. Para dejar una demostración limpia:

```bash
docker compose down -v
docker compose --env-file .env up -d --build
docker compose --env-file .env --profile seed run --rm tool
```
