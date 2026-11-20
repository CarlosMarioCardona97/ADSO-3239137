#!/bin/sh
# Arranque de la API: aplica las migraciones sobre la base vacía y sirve en el puerto 8000.
set -e
php artisan migrate --force
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
