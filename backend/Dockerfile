# syntax=docker/dockerfile:1

# ---- Composer dependencies --------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- Runtime ----------------------------------------------------------------
FROM php:8.3-cli-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl opcache

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

RUN php artisan package:discover --ansi \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

# The agent calls back into this API while a chat request is still open,
# so the built-in server needs several workers.
ENV PHP_CLI_SERVER_WORKERS=8 \
    LOG_CHANNEL=stderr

USER www-data
EXPOSE 8080

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
