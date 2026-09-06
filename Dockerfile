# ---- Stage 1: Base PHP runtime with all required extensions ----
FROM php:8.5-fpm-alpine AS base

# Install native C extensions (amqp, redis) for high-performance messaging and Redis caching;
# predis package acts as a pure-PHP fallback where native extensions are unavailable.
RUN apk add --no-cache icu-libs rabbitmq-c \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev rabbitmq-c-dev linux-headers \
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis \
    && docker-php-ext-install intl \
    && apk del .build-deps

# ---- Stage 2: Install vendor dependencies using exact runtime environment ----
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    && composer dump-env --empty prod

# ---- Stage 3: Production runtime ----
FROM base AS runtime

# PHP production tuning
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/.env.local.php ./.env.local.php

COPY bin ./bin
COPY config ./config
COPY public ./public
COPY src ./src

COPY composer.json symfony.lock ./

# Run Symfony post-install scripts (cache warmup etc.) with build-time secret
RUN APP_SECRET=build_time_dummy_secret_not_for_production DEFAULT_URI=http://localhost php bin/console cache:warmup --env=prod

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app \
    && chown -R app:app /app/var

USER app

EXPOSE 9000

CMD ["php-fpm"]
