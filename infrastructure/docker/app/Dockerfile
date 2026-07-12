# syntax=docker/dockerfile:1

# --- build: composer deps + frontend assets -----------------------------
# One combined stage (not composer-only + node-only) because the
# @laravel/vite-plugin-wayfinder Vite plugin shells out to `php artisan
# wayfinder:generate` during `pnpm run build` - the JS build needs a
# bootable PHP app with vendor/ already installed, not just Node.
FROM node:22-bookworm-slim AS build
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg \
    && curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(. /etc/os-release; echo $VERSION_CODENAME) main" > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-pgsql \
        php8.4-sqlite3 php8.4-zip php8.4-bcmath php8.4-gd php8.4-intl \
        unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN corepack enable pnpm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .

RUN cp .env.example .env \
    && php -r "file_put_contents('.env', preg_replace('/^APP_KEY=.*/m', 'APP_KEY='.base64_encode(random_bytes(32)), file_get_contents('.env')));" \
    && sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env \
    && touch database/build.sqlite \
    && DB_DATABASE=/app/database/build.sqlite php artisan package:discover --ansi \
    && DB_DATABASE=/app/database/build.sqlite php artisan migrate --force \
    && DB_DATABASE=/app/database/build.sqlite php artisan scribe:generate \
    && rm -f .env database/build.sqlite

ARG VITE_REVERB_HOST
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN pnpm run build

FROM dunglas/frankenphp:1-php8.4 AS runtime
WORKDIR /app

RUN install-php-extensions pdo_pgsql pgsql redis pcntl sockets gd intl zip bcmath opcache

RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m -s /usr/sbin/nologin appuser

COPY --from=build --chown=appuser:appuser /app/vendor ./vendor
COPY --from=build --chown=appuser:appuser /app/public/build ./public/build
COPY --from=build --chown=appuser:appuser /app/bootstrap/cache ./bootstrap/cache
COPY --from=build --chown=appuser:appuser /app/storage/app/private/scribe ./storage/app/private/scribe
COPY --from=build --chown=appuser:appuser /app/public/vendor/scribe ./public/vendor/scribe
COPY --chown=appuser:appuser . .

RUN rm -rf node_modules \
    && chown -R appuser:appuser storage bootstrap/cache

COPY --chmod=755 infrastructure/docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh

USER appuser
EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
