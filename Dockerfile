# ---- Stage 1: Install dependencies ----
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---- Stage 2: Production runtime ----
FROM php:8.5-fpm-alpine AS runtime

RUN apk add --no-cache icu-libs rabbitmq-c \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev rabbitmq-c-dev \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-install intl \
    && apk del .build-deps

# PHP production tuning
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor

COPY bin ./bin
COPY config ./config
COPY public ./public
COPY src ./src

COPY .env composer.json symfony.lock ./

# Run Symfony post-install scripts (cache warmup etc.)
RUN php bin/console cache:warmup --env=prod

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app \
    && chown -R app:app /app/var

USER app

EXPOSE 9000

CMD ["php-fpm"]
