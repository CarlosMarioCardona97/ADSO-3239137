# Plan técnico — Archive (Laravel + React)

Cómo se construye lo que dicen [`spec.md`](spec.md) y [`constitution.md`](constitution.md). El esquema **no** se describe
aquí: vive en [`data-model.md`](data-model.md). La forma del sistema está en [`architecture.md`](architecture.md).

## 1. Decisiones técnicas

| # | Decisión | Elección | Por qué |
|---|---|---|---|
| D-01 | Lenguaje y runtime del backend | PHP 8.3 | Exigido por el plan de mejoramiento |
| D-02 | Framework HTTP | Laravel 12, solo como API (`routes/api.php`, prefijo `/api/v1`) | Contrato en [`api-contract.md`](api-contract.md) |
| D-03 | Persistencia | Eloquent + migraciones de Laravel sobre PostgreSQL 16, esquema `archive` | El esquema es de las migraciones ([ADR-001](adr/adr-001-propiedad-del-esquema.md)) |
| D-04 | Concurrencia | Testigo de versión por expediente + `CHECK` de rango ([ADR-002](adr/adr-002-concurrencia-optimista.md)) | Última unidad (RNF-2) |
| D-05 | Fechas | Instantes UTC (`timestamptz`); el reloj es un puerto (`Clock`) | Probar vencimientos sin esperar 5 días |
| D-06 | Reporte | Una consulta agregada a través de un puerto de lectura ([ADR-004](adr/adr-004-reporte-agregado-y-congelado.md)) | Estable y barato |
| D-07 | Objetos de valor | `FilingNumber`, `Quantity`, `DateRange`: viven dentro de la fila de su dueño, sin tabla | No tienen identidad |
| D-08 | Carátulas | Binario en disco (`storage/app/covers`) detrás del puerto `CoverStorage`; la base guarda solo `cover_key` | Clave opaca, nunca ruta |
| D-09 | Contraseñas | bcrypt detrás del puerto `PasswordHasher`; el dominio nunca ve la clave en claro | Artículo IX |
| D-10 | Arranque | El comando `php artisan archive:bootstrap-admin` crea el admin inicial desde `ADMIN_USERNAME` / `ADMIN_PASSWORD` | La base no puede producir el hash |
| D-11 | Autenticación | JWT HS256 (`firebase/php-jwt`), 8 horas, clave en `JWT_SECRET` | Simple y sin estado |
| D-12 | Pruebas | Pest 3: unitarias con *fakes*, de característica (`Feature`) contra PostgreSQL real y de arquitectura con `arch()` | Pirámide de pruebas; artículo I verificable |
| D-13 | Frontend | React 18 + Vite + React Router; `fetch` nativo contra la API | Exigido por el plan; sin librerías de estado |
| D-14 | Sitio público | HTML y CSS estáticos, servidos por nginx | No llama a la API (T-23) |
| D-15 | Datos vencidos de demostración | El adaptador `SystemClock` acepta `CLOCK_OFFSET_DAYS` (por defecto `0`). `infra` levanta, solo con el perfil `seed`, una segunda instancia de la API con `-6` días sobre la misma base. El sembrador registra por ella los 3 préstamos vencidos | El sembrador usa **solo la API** y aun así puede crear vencidos; el dominio no se entera |

## 2. Configuración por entorno

| Variable | Obligatoria | Ejemplo |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | sí | `db`, `5432`, `archive`, `archive`, definida en `.env` |
| `DB_SEARCH_PATH` | sí | `archive,public` |
| `APP_KEY` | sí | generada con `php artisan key:generate` |
| `JWT_SECRET` | sí | cadena larga aleatoria |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | sí | definidos en `.env`, nunca en el repositorio |
| `FRONTEND_ORIGIN` | sí | `http://localhost:5173` |
| `CLOCK_OFFSET_DAYS` | no | `0` (solo la instancia de siembra usa `-6`) |

## 3. Estrategia de pruebas

| Nivel | Qué prueba | Contra qué | Carpeta |
|---|---|---|---|
| Dominio | Invariantes: máximo 3, vencimiento a 5 días, disponibles en rango | Nada: objetos puros | `tests/Unit/Domain` |
| Aplicación | Casos de uso: prestar, devolver, reporte | *Fakes* de los puertos | `tests/Unit/Application` |
| Integración | Repositorios y consulta del reporte | PostgreSQL real | `tests/Feature/Persistence` |
| Contrato | Cuerpo de error único y códigos de estado | La API en memoria (`$this->postJson`) | `tests/Feature/Http` |
| Arquitectura | Artículos I, II y III | `arch()` de Pest | `tests/Architecture` |
| Concurrencia | Dos préstamos de la última unidad | PostgreSQL real, dos conexiones | `tests/Feature/Concurrency` |

Meta: 15 archivos de prueba o más en `api`.

## 4. Orden de construcción

Esqueleto y salud → migración inicial con semilla → dominio → casos de uso con *fakes* → adaptadores → API → pruebas de
contrato y concurrencia → `infra` → `tool` → `app` → `page`. El detalle, con evidencia, está en [`tasks.md`](tasks.md).
