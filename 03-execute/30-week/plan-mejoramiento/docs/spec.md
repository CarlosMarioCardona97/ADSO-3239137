# Especificación — Archive

**Qué se construye y por qué.** Un sistema para que el archivo de una alcaldía municipal lleve su catálogo de
expedientes, los funcionarios que los solicitan y los préstamos, y vea qué expedientes se piden más.

Se rige por [`constitution.md`](constitution.md). El modelo de datos vive solo en [`data-model.md`](data-model.md) y el
contrato HTTP solo en [`api-contract.md`](api-contract.md).

---

## 1. Actores

| Actor | Qué hace | Cómo se autentica |
|---|---|---|
| **Administrador** (`admin`) | Mantiene el catálogo de expedientes y los funcionarios; da de alta archivistas; ve todos los reportes | Usuario y contraseña → token |
| **Archivista** (`archivist`) | Registra préstamos y devoluciones; consulta catálogo, funcionarios y vencidos | Usuario y contraseña → token |
| **Funcionario** | Empleado de la alcaldía que pide expedientes prestados. **No entra al sistema**: es un dato, no un usuario | — |

El rol `admin` lo crea el despliegue a partir de variables de entorno. **Nadie lo otorga en ejecución.**

## 2. Alcance

**Dentro:** catálogo de expedientes con carátula digitalizada opcional, cinco categorías fijas (contratos, personal,
predial, actas, jurídico), funcionarios, préstamos con varias líneas, devolución por línea, expedientes vencidos, reporte
de expedientes más solicitados, inicio de sesión con roles.

**Fuera, y no se reabre:** sanciones disciplinarias, reservas, renovaciones de plazo, notificaciones por correo o SMS,
digitalización del contenido del expediente, importación masiva, préstamos entre sedes, transferencias documentales,
atributos del expediente distintos a los del §3 (sin folios, sin serie documental, sin fechas extremas).

## 3. Historias de usuario y criterios de aceptación

### HU-01 · Iniciar sesión
Como usuario quiero autenticarme para operar según mi rol.
- **CA-01.1** Con credenciales válidas se recibe un token con vigencia de 8 horas.
- **CA-01.2** Con credenciales inválidas responde 401 con un mensaje que **no** dice si falló el usuario o la clave.
- **CA-01.3** Todo endpoint, salvo el inicio de sesión y `/health`, exige token. Sin token: 401. Rol insuficiente: 403.

### HU-02 · Dar de alta archivistas (admin)
- **CA-02.1** El admin crea un usuario con nombre, clave y rol `archivist`.
- **CA-02.2** El nombre de usuario se guarda en minúsculas y sin espacios al inicio ni al final; es único. Repetido: 409.
- **CA-02.3** Pedir el rol `admin` por la API responde 422. La clave nunca se devuelve ni se registra en logs.

### HU-03 · Gestionar expedientes (admin)
- **CA-03.1** Crear un expediente con título (asunto), dependencia productora, número de radicado, categoría y total de
  unidades. Las unidades disponibles arrancan iguales al total.
- **CA-03.2** El número de radicado tiene el formato `AAAA-NNNNNN` (año de cuatro dígitos, guion y consecutivo de seis
  dígitos, por ejemplo `2024-000153`) y es único entre expedientes activos. Repetido: 409.
- **CA-03.3** Total de unidades ≥ 1. Cambiar el total ajusta las disponibles en la misma diferencia y **nunca** deja
  disponibles < 0 (422).
- **CA-03.4** Subir la carátula (JPG/PNG, máx. 2 MB) guarda una **clave opaca**; sin carátula el campo es `null`, nunca
  cadena vacía.
- **CA-03.5** Eliminar un expediente es **baja lógica**: deja de aparecer en el catálogo, pero sigue en los préstamos
  pasados. Un expediente con préstamos abiertos no se da de baja (409).

### HU-04 · Buscar expedientes
- **CA-04.1** Búsqueda por texto parcial en título, dependencia productora o número de radicado; filtro por categoría;
  solo expedientes activos; orden por título; paginada (`page`, `size`, máx. 50).
- **CA-04.2** La respuesta incluye el total de resultados.
- **CA-04.3** Consultar un expediente por un id inexistente o dado de baja: 404.

### HU-05 · Gestionar funcionarios
- **CA-05.1** El admin y el archivista crean funcionarios con nombre completo y número de documento. El documento es
  único: repetido 409.
- **CA-05.2** Se listan y se buscan por nombre o documento, paginados.
- **CA-05.3** Los funcionarios no se eliminan.

### HU-06 · Registrar un préstamo
- **CA-06.1** Un préstamo es de **un** funcionario, lo registra el usuario autenticado y trae de 1 a 3 líneas.
- **CA-06.2** No se repite el mismo expediente en un préstamo (422).
- **CA-06.3** Cada línea descuenta **1** unidad disponible en la misma operación. Sin unidades: 409 y **no se registra
  nada**.
- **CA-06.4** Un funcionario no puede tener más de **3 expedientes sin devolver** en total (422 con el conteo actual).
- **CA-06.5** Un funcionario con algún expediente **vencido** no puede pedir otro (409).
- **CA-06.6** La fecha de vencimiento es `loaned_at + 5 días`. La línea congela título, número de radicado y nombre de
  la categoría **en ese instante**.
- **CA-06.7** El préstamo registrado no se edita ni se borra.

### HU-07 · Registrar una devolución
- **CA-07.1** Se devuelve **una línea** a la vez. Se llena `returned_at` y se suma 1 a las disponibles.
- **CA-07.2** Devolver una línea ya devuelta: 409. **Nunca** se suma dos veces.
- **CA-07.3** Las disponibles jamás superan el total (lo garantiza la base de datos).
- **CA-07.4** Cuando todas las líneas están devueltas, el préstamo figura como **cerrado**.

### HU-08 · Ver un préstamo y listar préstamos
- **CA-08.1** Consultar un préstamo trae sus líneas y su estado calculado (`open`, `overdue`, `closed`).
- **CA-08.2** Se listan por rango de fechas, estado y funcionario, en orden de fecha descendente, paginados.
- **CA-08.3** El estado **se calcula**, no se almacena.

### HU-09 · Expedientes vencidos
- **CA-09.1** Lista las líneas sin devolver con `due_at` anterior a ahora: expediente, funcionario y días de retraso.
- **CA-09.2** Ordenadas de más a menos días de retraso. Solo `admin` y `archivist`.

### HU-10 · Reporte de expedientes más solicitados
- **CA-10.1** Dado un rango de fechas, una fila por **expediente y nombre de categoría congelado**, con la cantidad de
  veces que se prestó, ordenado de mayor a menor.
- **CA-10.2** Fin anterior al inicio: 422. Rango sin préstamos: lista vacía, no error.
- **CA-10.3** El reporte **no** se desglosa por archivista ni por funcionario.
- **CA-10.4** El reporte de un periodo cerrado **no cambia** aunque luego se renombre el expediente o su categoría.
- **CA-10.5** Solo `admin`.

## 4. Reglas de negocio

| # | Regla |
|---|---|
| RN-1 | Unidades disponibles entre 0 y total, siempre. |
| RN-2 | Plazo de préstamo: 5 días. Máximo 3 expedientes sin devolver por funcionario. |
| RN-3 | Un funcionario con vencidos no recibe más expedientes hasta devolverlos. |
| RN-4 | Lo congelado en la línea (título, número de radicado, categoría) no sigue al catálogo. |
| RN-5 | Los préstamos y las devoluciones no se borran. Los expedientes solo tienen baja lógica. |
| RN-6 | Las fechas son instantes UTC. Mensajes al usuario en español; código y columnas en inglés. |

## 5. Requisitos no funcionales

| # | Requisito | Cómo se mide |
|---|---|---|
| RNF-1 | La búsqueda de expedientes responde en menos de 300 ms con 5 000 expedientes | Prueba de carga con el sembrador de `tool` |
| RNF-2 | Dos archivistas entregando la **última unidad** a la vez: uno gana y el otro recibe 409 | Prueba concurrente |
| RNF-3 | La contraseña nunca viaja ni se guarda en claro; nunca aparece en logs | Revisión de logs y de respuestas |
| RNF-4 | Todo error HTTP usa el mismo cuerpo (ver contrato) | Prueba de contrato |
| RNF-5 | El sistema levanta con un solo comando y la base arranca vacía | `README` de `infra` |
