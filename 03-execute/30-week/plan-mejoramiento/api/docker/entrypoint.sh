#!/bin/sh
# Arranque de la API: aplica las migraciones sobre la base vacía, crea el admin inicial
# desde el entorno (T-17) y sirve en el puerto 8000 con varios workers (RNF-2).
set -e
php artisan migrate --force
php artisan archive:bootstrap-admin
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
