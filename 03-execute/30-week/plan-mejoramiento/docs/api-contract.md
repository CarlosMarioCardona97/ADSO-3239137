# Contrato de la API — Archive

El contrato HTTP es **independiente del lenguaje**: la API en Laravel lo implementa y el frontend en React lo consume.
Qué hace cada operación está en [`spec.md`](spec.md); qué se guarda, en [`data-model.md`](data-model.md).

## 1. Convenciones

| Tema | Regla |
|---|---|
| Base | `/api/v1` |
| Formato | JSON, UTF-8. Nombres de campo en `snake_case` |
| Identificadores | UUID v4 como texto |
| Fechas | ISO 8601 en UTC, con `Z` (`2026-11-14T14:15:00Z`) |
| Dinero | No hay. El sistema no maneja importes |
| Autenticación | `Authorization: Bearer <token>` en todo, salvo `POST /auth/login` y `GET /health` |
| Paginación | `?page=1&size=20`. `size` máximo 50. La respuesta es `{ "items": [...], "page": 1, "size": 20, "total": 134 }` |
| Idioma | Mensajes de error en español; campos y códigos en inglés |

### Cuerpo de error (único para todos los errores)

```json
{
  "code": "DOSSIER_NOT_AVAILABLE",
  "message": "No quedan unidades disponibles del expediente 2024-000153.",
  "details": [ { "field": "items[0].dossier_id", "issue": "sin unidades" } ]
}
```

`details` puede ir vacío. **Nunca** incluye trazas, SQL, rutas del servidor ni la contraseña.

### Códigos de estado

| Estado | Cuándo |
|---|---|
| 200 / 201 / 204 | Éxito / creado / sin contenido |
| 401 | Sin token, token inválido o vencido, credenciales inválidas |
| 403 | Rol insuficiente |
| 404 | No existe (o está dado de baja) |
| 409 | Conflicto de estado: duplicado, sin unidades, ya devuelto, funcionario con vencidos, expediente con préstamos abiertos |
| 422 | Validación: campo inválido, regla de entrada rota, rango de fechas invertido |

## 2. Códigos de error de negocio

| `code` | Estado | Origen |
|---|---|---|
| `INVALID_CREDENTIALS` | 401 | CA-01.2 |
| `UNAUTHENTICATED` | 401 | CA-01.3 |
| `FORBIDDEN_ROLE` | 403 | CA-01.3 |
| `NOT_FOUND` | 404 | Cualquier recurso |
| `USERNAME_TAKEN` | 409 | CA-02.2 |
| `FILING_NUMBER_TAKEN` | 409 | CA-03.2 |
| `DOCUMENT_TAKEN` | 409 | CA-05.1 |
| `DOSSIER_HAS_OPEN_LOANS` | 409 | CA-03.5 |
| `DOSSIER_NOT_AVAILABLE` | 409 | CA-06.3, RNF-2 |
| `OFFICIAL_HAS_OVERDUE` | 409 | CA-06.5 |
| `ITEM_ALREADY_RETURNED` | 409 | CA-07.2 |
| `OFFICIAL_LIMIT_EXCEEDED` | 422 | CA-06.4 |
| `DUPLICATE_DOSSIER_IN_LOAN` | 422 | CA-06.2 |
| `UNITS_BELOW_LOANED` | 422 | CA-03.3 |
| `VALIDATION_ERROR` | 422 | Cualquier campo inválido |
| `INVALID_DATE_RANGE` | 422 | CA-10.2 |

## 3. Endpoints

### 3.1 Autenticación y salud

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/auth/login` | público | Devuelve el token |
| GET | `/health` | público | `{ "status": "ok" }` |

`POST /auth/login`

```json
// petición
{ "username": "ana", "password": "********" }
// 200
{ "access_token": "eyJ...", "token_type": "Bearer", "expires_in": 28800, "role": "archivist" }
```

### 3.2 Usuarios

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/users` | admin | Crea un archivista |

```json
// petición  (role solo admite "archivist")
{ "username": "Ana ", "password": "********", "role": "archivist" }
// 201  (sin password ni password_hash)
{ "id": "…", "username": "ana", "role": "archivist" }
```

### 3.3 Categorías

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/categories` | autenticado | Las cinco categorías, en orden por nombre |

### 3.4 Expedientes

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/dossiers` | autenticado | Búsqueda: `q`, `category_id`, `page`, `size` |
| GET | `/dossiers/{id}` | autenticado | Detalle |
| POST | `/dossiers` | admin | Crea |
| PUT | `/dossiers/{id}` | admin | Reemplaza título, dependencia productora, categoría y total de unidades |
| DELETE | `/dossiers/{id}` | admin | Baja lógica (204) |
| PUT | `/dossiers/{id}/cover` | admin | Sube la carátula (`multipart/form-data`, campo `file`) |
| GET | `/dossiers/{id}/cover` | autenticado | Devuelve el binario de la carátula o 404 |
| DELETE | `/dossiers/{id}/cover` | admin | Quita la carátula (204) |

Expediente (respuesta):

```json
{
  "id": "…", "title": "Contrato de obra pública 045 — pavimentación barrio Las Granjas",
  "producing_office": "Secretaría de Infraestructura",
  "filing_number": "2024-000153", "category": { "id": "…", "name": "Contratos" },
  "total_units": 2, "available_units": 1, "cover_key": null
}
```

`POST /dossiers` (petición): `title`, `producing_office`, `filing_number`, `category_id`, `total_units`. Las disponibles
las calcula el servidor. `PUT /dossiers/{id}` no cambia el número de radicado.
`cover_key` es una clave opaca: para pintar la carátula se usa `GET /dossiers/{id}/cover`.

### 3.5 Funcionarios

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/officials` | admin, archivist | Lista y busca: `q`, `page`, `size` |
| GET | `/officials/{id}` | admin, archivist | Detalle con `active_loans` (conteo de expedientes sin devolver) |
| POST | `/officials` | admin, archivist | Crea: `full_name`, `document_number` |

### 3.6 Préstamos

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/loans` | admin, archivist | Registra un préstamo |
| GET | `/loans` | admin, archivist | Lista: `from`, `to`, `status`, `official_id`, `page`, `size` |
| GET | `/loans/overdue` | admin, archivist | Líneas vencidas |
| GET | `/loans/{id}` | admin, archivist | Detalle con líneas y estado |
| POST | `/loans/{loan_id}/items/{item_id}/return` | admin, archivist | Devuelve una línea |

`POST /loans`

```json
// petición  (1 a 3 elementos, sin expedientes repetidos)
{ "official_id": "…", "items": [ { "dossier_id": "…" }, { "dossier_id": "…" } ] }
// 201
{
  "id": "…", "official_id": "…", "loaned_at": "2026-11-14T14:15:00Z", "status": "open",
  "items": [
    { "id": "…", "dossier_id": "…", "dossier_title": "Contrato de obra pública 045 — pavimentación barrio Las Granjas",
      "filing_number": "2024-000153", "category_name": "Contratos",
      "due_at": "2026-11-19T14:15:00Z", "returned_at": null }
  ]
}
```

`status` se calcula: `open` (hay líneas sin devolver y ninguna vencida), `overdue` (alguna sin devolver y vencida),
`closed` (todas devueltas).

`POST /loans/{loan_id}/items/{item_id}/return` → 200 con la línea (`returned_at` lleno).

`GET /loans/overdue` → `items`: `{ "loan_id", "item_id", "dossier_title", "filing_number", "official_name", "due_at",
"days_late" }`. Aquí sí se muestra el nombre del funcionario, porque es un listado operativo; **el reporte del §3.7 no**.

### 3.7 Reporte

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/reports/most-requested` | admin | `from` y `to` obligatorios (fecha `AAAA-MM-DD`, ambos inclusive) |

```json
{
  "from": "2026-11-01", "to": "2026-11-30",
  "rows": [
    { "dossier_id": "…", "dossier_title": "Contrato de obra pública 045 — pavimentación barrio Las Granjas",
      "filing_number": "2024-000153", "category_name": "Contratos", "times_requested": 12 }
  ]
}
```

Una fila por `dossier_id + dossier_title + category_name` congelados. Orden: `times_requested` descendente y luego
título. Sin funcionarios ni archivistas.

## 4. Seguridad

| Aspecto | Regla |
|---|---|
| Token | JWT firmado (HS256), 8 horas, con `sub` y `role`. Sin datos personales dentro |
| Contraseñas | Solo se guarda el hash (bcrypt); el hash nunca sale en ninguna respuesta |
| Autorización | Se evalúa en cada endpoint por rol; un 403 no revela si el recurso existe |
| CORS | Solo el origen del frontend, configurado por entorno (`FRONTEND_ORIGIN`) |
| Subida de carátula | Solo `image/jpeg` e `image/png`, máx. 2 MB; el tipo se valida por contenido, no por extensión |
