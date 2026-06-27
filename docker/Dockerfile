# FreeV2Ray bot — single image used for web (FrankenPHP), queue and scheduler.
FROM dunglas/frankenphp:1-php8.3

# Composer binary
COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# PHP extensions needed by Laravel + this app.
RUN install-php-extensions \
    pdo_mysql \
    redis \
    intl \
    zip \
    bcmath \
    gd \
    opcache \
    pcntl

WORKDIR /app

# Install PHP deps first (better layer caching). Retry to ride out transient
# GitHub/codeload download hiccups (HTTP 400/429) that would otherwise abort the
# whole build on a single failed package download.
COPY composer.json composer.lock ./
RUN for i in 1 2 3 4 5; do \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction && exit 0; \
        echo "composer install failed (attempt $i/5), retrying in 15s..."; \
        sleep 15; \
    done; \
    exit 1

# App source.
COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    # Publish Filament assets into public/ (throwaway key just for this command).
    && APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" php artisan filament:assets || true \
    && chown -R www-data:www-data storage bootstrap/cache public

# Server config (php_server + auto HTTPS).
COPY docker/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443

# Default command runs the web server; queue/scheduler override `command` in compose.
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
