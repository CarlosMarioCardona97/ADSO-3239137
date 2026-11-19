# Constitución — Archive (Laravel + React)

Principios innegociables del proyecto **Archive**, préstamo de expedientes de un archivo municipal. Cada artículo trae
**cómo se verifica**: un principio que nadie puede comprobar es solo una intención. Un cambio que viole un artículo se
rechaza sin discutir el resto.

---

## Artículo I — El dominio no depende de nada

`api/app/Domain/` solo usa PHP puro: clases `final`, propiedades `readonly`, `DateTimeImmutable`. No importa nada de
`Illuminate\*` (ni Eloquent, ni `Request`, ni facades) ni de las capas `Application` o `Infrastructure`.

**Por qué:** así las reglas del archivo (máximo 3 expedientes, 5 días, unidades entre 0 y total) se prueban sin base de
datos, sin servidor y sin contenedor, y se puede cambiar de ORM sin tocarlas.
**Verificación:** prueba de arquitectura con Pest (`tests/Architecture/DomainTest.php`):
`arch()->expect('App\Domain')->not->toUse(['Illuminate', 'App\Application', 'App\Infrastructure'])`.

## Artículo II — El núcleo declara sus puertos; el mundo los implementa

`api/app/Application/` solo conoce el dominio. Lo que necesita del exterior (repositorios, reloj, hash de claves,
almacenamiento de carátulas, emisión de tokens) lo expresa como **interfaces que ella misma define** en
`App\Application\Port`. Ningún tipo de Laravel ni de Eloquent aparece en la firma de un puerto.

**Verificación:** prueba de arquitectura: `App\Application` no usa `App\Infrastructure` ni `Illuminate`. Las pruebas de
casos de uso corren con *fakes* en memoria.

## Artículo III — Un único punto de composición

Un solo proveedor de servicios (`App\Providers\PortBindingsServiceProvider`) une cada puerto con su adaptador. Fuera de él no
se instancia infraestructura.
**Verificación:** prueba de arquitectura: las clases de `App\Infrastructure` solo se referencian desde ese proveedor y desde
`App\Infrastructure` mismo.

## Artículo IV — SOLID no es decorativo

| Principio | Aplicación concreta | Verificación |
|---|---|---|
| **SRP** | Un servicio de aplicación = un caso de uso. `LendDossiersService` presta y nada más | Un servicio con dos razones para cambiar se parte |
| **OCP** | Cambiar el almacenamiento de carátulas (disco → S3) no toca dominio ni aplicación | El adaptador nuevo solo agrega una línea en la composición |
| **LSP** | Los *fakes* sustituyen al adaptador real sin cambiar expectativas | La misma prueba pasa contra el *fake* y el real |
| **ISP** | Puertos pequeños: `Clock`, `PasswordHasher`, `TokenIssuer`, `CoverStorage` | Ningún implementador deja métodos sin usar |
| **DIP** | El puerto lo define quien lo consume | Los puertos viven en `App\Application\Port` |

## Artículo V — El esquema es propiedad de las migraciones

`infra` levanta un PostgreSQL **vacío**. Todo el DDL (tablas, índices, restricciones y la semilla de las cinco categorías)
vive en las migraciones de Laravel (`api/database/migrations`). Ver [ADR-001](adr/adr-001-propiedad-del-esquema.md).
**Verificación:** `infra` no contiene `CREATE TABLE`.

## Artículo VI — Las invariantes que la base puede imponer, la base las impone

Una regla que solo vive en código protege a la aplicación, no a los datos. Toda invariante que el motor pueda expresar
(`CHECK`, `UNIQUE`, FK) se declara en él. Lo que no esté aplicado se marca como **pendiente** en
[`data-model.md`](data-model.md); no se promete.
**Verificación:** las consultas de `data-model.md` §10 devuelven lo que declara el documento.

## Artículo VII — Lo derivado se calcula, no se almacena

El estado de un préstamo (`open`, `overdue`, `closed`) y los días de retraso se calculan. No existe una columna para ellos.
**Verificación:** `data-model.md` no lista ninguna columna `status` ni `days_late`.

## Artículo VIII — Los valores congelados no siguen al catálogo

La línea de préstamo guarda el título, el número de radicado y la categoría del expediente **en el instante del préstamo**.
Ningún reporte los lee del catálogo vivo.
**Verificación:** una prueba renombra un expediente después de prestarlo y comprueba que el reporte conserva el título viejo
([ADR-004](adr/adr-004-reporte-agregado-y-congelado.md)).

## Artículo IX — Ningún secreto entra al repositorio ni a un log

Ni contraseñas, ni hashes precalculados, ni la clave de firma del token (`APP_KEY`, `JWT_SECRET`). Todo secreto viene del
entorno. El `.env` real está en `.gitignore`; solo se versiona `.env.example`.
**Verificación:** `git ls-files | grep -E '(^|/)\.env$'` no devuelve nada; ninguna respuesta HTTP incluye `password` ni
`password_hash`.

## Artículo X — Gana el motor

Si el documento de datos y la base no coinciden, el documento está roto y se corrige el documento.

## Artículo XI — Idioma

Código, clases, columnas, rutas y códigos de error en **inglés**. Documentación y mensajes al usuario en **español**.
Las fechas son instantes **UTC**.

## Artículo XII — Nada se da por hecho sin evidencia

Una tarea de [`tasks.md`](tasks.md) está terminada cuando su **evidencia** existe y se puede ejecutar, no cuando «ya está
programada».
