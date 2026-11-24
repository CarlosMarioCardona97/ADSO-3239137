# page — Sitio público de Archive

Sitio estático que **presenta el producto** a la alcaldía (T-23). Es HTML y CSS puros: no llama a la API, no usa
frameworks ni recursos externos, y se abre aunque no haya conexión.

| Archivo | Contenido |
|---|---|
| `index.html` | Portada, funciones, reglas, cómo funciona y permisos por rol |
| `styles.css` | Estilos; se adapta a escritorio, tableta (≤ 900 px) y celular (≤ 560 px) |
| `assets/logo.svg` | Logotipo y favicon |

## Ver el sitio

- Sin instalar nada: abrir `index.html` en el navegador.
- Con el resto del sistema: `docker compose --env-file .env up` en [`../infra`](../infra) y abrir
  `http://localhost:8088`.

## Evidencia de T-23

1. Se abre sin la API (apague los contenedores y abra `index.html`).
2. Es correcto en móvil y escritorio: en las herramientas del navegador (F12 → modo dispositivo) pruebe 375 px,
   768 px y 1280 px de ancho; no aparece barra de desplazamiento horizontal.
