FROM php:8.4-cli-alpine AS base

WORKDIR /app
ENV APP_ENV=prod
EXPOSE 9501

CMD bin/console swoole:server:run

RUN --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions apcu @composer intl ds opcache pcntl pdo_pgsql sysvsem swoole xsl

COPY .docker/php/ini/zz-php.ini /usr/local/etc/php/conf.d/zz-php.ini

FROM base AS dev-local

ENV APP_ENV=dev

RUN --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions pcov xdebug

FROM dev-local AS dev

COPY composer.json composer.lock ./

RUN composer install --prefer-dist --no-scripts --no-interaction --no-progress --no-cache --optimize-autoloader

COPY assets/ assets/
COPY bin/ bin/
COPY config/ config/
COPY migrations/ migrations/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY tests/ tests/
COPY translations/ translations/
COPY .env .env.test ecs.php importmap.php phpstan.dist.neon phpstan-baseline.neon phpunit.xml.dist rector.php ./

RUN bin/console cache:warmup \
    && bin/console tailwind:build --minify  \
    && bin/console importmap:install \
    && bin/console asset-map:compile \
    && bin/console assets:install --symlink

FROM base AS prod

HEALTHCHECK --start-period=5s --interval=5s --timeout=1s --retries=3 CMD curl -sS localhost:9501/health || exit 1

COPY composer.json composer.lock ./

RUN composer install --prefer-dist --no-scripts --no-interaction --no-progress --no-cache --optimize-autoloader --no-dev

COPY assets/ assets/
COPY bin/ bin/
COPY config/ config/
COPY migrations/ migrations/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY translations/ translations/
COPY .env .env.prod importmap.php ./

RUN bin/console cache:warmup  \
    && bin/console tailwind:build --minify  \
    && bin/console importmap:install \
    && bin/console asset-map:compile \
    && bin/console assets:install --symlink
