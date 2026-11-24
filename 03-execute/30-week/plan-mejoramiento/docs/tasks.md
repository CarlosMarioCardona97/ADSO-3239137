# Tareas — Archive (Laravel + React)

Cada tarea termina cuando su **evidencia** existe y se puede ejecutar (artículo XII). Estado inicial de todas:
**pendiente**. Las rutas de prueba son relativas a `api/`.

| # | Tarea | Depende de | Evidencia |
|---|---|---|---|
| T-01 | Esqueleto del proyecto Laravel y `GET /api/v1/health` | — | `GET /api/v1/health` responde `{"status":"ok"}`; `tests/Feature/Http/HealthTest.php` |
| T-02 | Migración inicial: seis tablas, FK, índices únicos y las cinco categorías sembradas | T-01 | Las consultas de `data-model.md` §10 devuelven lo documentado; ninguna columna con `DEFAULT` |
| T-03 | Dominio: `FilingNumber`, `Quantity`, `DateRange` (objetos de valor) | T-01 | `tests/Unit/Domain/ValueObjectsTest.php`: radicado `AAAA-NNNNNN`, cantidad > 0, fin ≥ inicio |
| T-04 | Dominio: `Dossier` (`withdraw`, `restock`, baja lógica) | T-03 | `tests/Unit/Domain/DossierTest.php`: nunca < 0, nunca > total |
| T-05 | Dominio: `Loan` (`addItem`, `returnItem`, estado calculado, 5 días, máx. 3) | T-04 | `tests/Unit/Domain/LoanTest.php` con un `Clock` falso: `open`, `overdue`, `closed` |
| T-06 | Dominio: `Official` y `User` (normalización de nombre, rol válido) | T-03 | `tests/Unit/Domain/UserTest.php`: `"Ana "` queda `ana`; rol `admin` por API rechazado |
| T-07 | Puertos y casos de uso de **autenticación** (`LoginService`, `CreateArchivistService`) con *fakes* | T-06 | `tests/Unit/Application/AuthServicesTest.php`: HU-01 y HU-02 pasan contra *fakes* |
| T-08 | Puertos y casos de uso de **expedientes** (crear, buscar, editar, baja, carátula) con *fakes* | T-04 | `tests/Unit/Application/DossierServicesTest.php`: HU-03 y HU-04 |
| T-09 | Caso de uso **prestar** (`LendDossiersService`) con *fakes* | T-05, T-08 | `tests/Unit/Application/LendDossiersServiceTest.php`: HU-06 completa (límites, vencidos, sin unidades) |
| T-10 | Casos de uso **devolver** y **vencidos** | T-09 | `tests/Unit/Application/ReturnAndOverdueTest.php`: HU-07 y HU-09; devolver dos veces → `ITEM_ALREADY_RETURNED` |
| T-11 | Caso de uso **reporte de más solicitados** y su puerto de lectura | T-09 | `tests/Unit/Application/MostRequestedReportServiceTest.php`: HU-10 |
| T-12 | Adaptador de persistencia: repositorios de expediente, funcionario y usuario | T-02, T-08 | `tests/Feature/Persistence/RepositoriesTest.php` contra PostgreSQL real |
| T-13 | Adaptador de persistencia: repositorio de préstamos + testigo de versión | T-12, T-09 | `tests/Feature/Concurrency/LastUnitTest.php`: dos préstamos de la última unidad → uno 201, otro 409 (RNF-2) |
| T-14 | Adaptador de seguridad: hash de claves, token JWT y reloj | T-07 | `tests/Feature/Http/AuthTest.php`: la clave nunca aparece en logs ni respuestas (RNF-3) |
| T-15 | Adaptador de carátulas (disco) | T-08 | `tests/Feature/Http/CoverTest.php`: PNG de 2 MB ok; 3 MB → 422; sin carátula → `cover_key` nulo |
| T-16 | Adaptador HTTP: rutas de [`api-contract.md`](api-contract.md), cuerpo de error único, autorización por rol | T-10, T-11, T-14, T-15 | `tests/Feature/Http/*Test.php`: recorren todas las rutas y todos los códigos del §2 |
| T-17 | Composición y arranque (admin inicial desde el entorno) | T-16 | `php artisan archive:bootstrap-admin` sobre la base vacía permite iniciar sesión como admin |
| T-18 | Bajar a la base las invariantes **solo dominio**: `ck_dossier_total_positive`, `ck_user_role` | T-12 | Un `INSERT` por `psql` que las viole falla; `data-model.md` §4 se actualiza |
| T-19 | Pruebas de arquitectura (artículos I, II y III) | T-17 | `tests/Architecture/*Test.php` pasan, y fallan si se introduce una dependencia prohibida |
| T-20 | `infra`: `docker compose up` levanta PostgreSQL vacío, API y frontend | T-17 | Un comando; la base arranca sin tablas hasta que corren las migraciones |
| T-21 | `tool`: sembrador de demostración **por la API** (≥ 40 expedientes, 10 funcionarios, 15 préstamos, 3 vencidos) | T-17 | Tras correrlo, `/loans/overdue` devuelve 3 líneas y el reporte tiene filas |
| T-22 | `app`: login, catálogo, funcionarios, prestar, devolver, vencidos, reporte | T-16 | Recorrido manual de punta a punta descrito en `app/README.md` |
| T-23 | `page`: sitio público estático de presentación del producto | — | Se abre sin la API; correcto en móvil y en escritorio |

## Definición de terminado

El plan de mejoramiento exige el proyecto **totalmente terminado**: las 23 tareas con su evidencia, las 10 historias, las
6 reglas, los 5 requisitos no funcionales y 15 archivos de prueba o más. Al cerrar cada tarea se cambia su estado en esta
tabla en el mismo commit que trae la evidencia.

## Estado al 23 de noviembre de 2026

| Tareas | Estado | Evidencia |
|---|---|---|
| T-01 a T-19 | **terminadas** | `api/tests`: 20 archivos, 106 pruebas en verde (`api/README.md` § Pruebas) |
| T-20 | **terminada** | `docker compose --env-file .env up --build` en `infra` levanta base, API y sitio |
| T-23 | **terminada** | `page/index.html` abre sin la API; adaptable a 375, 768 y 1280 px |
| T-21, T-22 | pendientes | — |
