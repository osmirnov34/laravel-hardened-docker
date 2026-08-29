FROM node:24-alpine3.24@sha256:d32cdf619f63fe0471182d08996dd516c6275bb5fd31ae06e55a570bd9e1ad43 AS assets-builder

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js ./

RUN npm run build

FROM php:8.5-fpm-alpine3.24@sha256:9dc81f4086ea5402227a6bcc489b04b4baba12394624d9621faa92ed812fb8ee AS php-base

ARG TZ=UTC

RUN apk add --no-cache fcgi tzdata \
    && cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo "$TZ" > /etc/timezone \
    && apk del tzdata

RUN sed -i '/^user = /d' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i '/^group = /d' /usr/local/etc/php-fpm.d/www.conf

ENV APP_HOME=/var/www

WORKDIR $APP_HOME

FROM php-base AS app-builder

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Explicit allowlist instead of `COPY . .`: keeps dotfiles, docker/, tests/, and
# anything dropped into the repo root out of the image without relying on .dockerignore alone.
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY public/ ./public/
COPY resources/ ./resources/
COPY routes/ ./routes/
COPY artisan .

# 700: only www-data ever reads/writes here (single process, single user) — no group/other access needed.
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/private \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache && chmod -R 700 storage bootstrap/cache

COPY --from=assets-builder --chown=www-data:www-data /app/public/build $APP_HOME/public/build

RUN composer dump-autoload --optimize --no-dev

FROM php-base AS php-production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --chown=www-data:www-data --chmod=400 docker/php/production/conf.d/ /usr/local/etc/php/conf.d/
COPY --chown=www-data:www-data --chmod=400 docker/php/production/php-fpm.d/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf

COPY --from=app-builder --chown=www-data:www-data $APP_HOME $APP_HOME
COPY --from=app-builder --chown=www-data:www-data $APP_HOME/storage $APP_HOME/storage-init

COPY --chown=www-data:www-data --chmod=500 docker/php/php-entrypoint /usr/local/bin/php-entrypoint

RUN rm -f $APP_HOME/composer.lock

USER www-data
ENTRYPOINT ["/usr/local/bin/php-entrypoint"]
EXPOSE 9000
CMD ["php-fpm"]

FROM nginxinc/nginx-unprivileged:alpine3.24@sha256:c3fed6436b61d2bf2201ec032c35c000871f7ed062dea5d586bc6bf4d0fdd140 AS nginx-base

ARG TZ=UTC

USER root

RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo "$TZ" > /etc/timezone \
    && apk del tzdata

ENV APP_HOME=/var/www

WORKDIR $APP_HOME

COPY --from=app-base --chown=nginx:nginx $APP_HOME/public $APP_HOME/public

USER nginx

FROM nginx-base AS nginx-production

USER nginx
EXPOSE 8081 8443

FROM nginx-base AS nginx-dev

USER nginx
EXPOSE 8080
