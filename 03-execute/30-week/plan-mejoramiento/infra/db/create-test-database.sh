#!/bin/sh
# Crea una segunda base VACÍA para las pruebas automatizadas (archive_test).
# No crea tablas: el esquema lo ponen las migraciones de la API (ADR-001).
set -e
psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "CREATE DATABASE ${POSTGRES_DB}_test OWNER $POSTGRES_USER;"
