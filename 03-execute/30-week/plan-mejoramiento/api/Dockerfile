# Imagen de la API Archive: PHP 8.3 con la extensión de PostgreSQL.
FROM php:8.3-cli-alpine
RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo_pgsql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
# Si vendor/ ya existe (copia local) composer solo lo verifica; en un clon limpio lo descarga.
RUN composer install --no-interaction --no-scripts --prefer-dist \
    && composer dump-autoload --optimize \
    && chmod +x docker/entrypoint.sh
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
