FROM php:8.4-cli-alpine AS base

WORKDIR /app
ENV APP_ENV=prod

CMD bin/console swoole:server:run

RUN --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions apcu @composer intl ds opcache pcntl pdo_pgsql sysvsem swoole xsl

FROM base AS dev-local

ENV APP_ENV=dev

RUN --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions pcov xdebug

COPY .docker/php/ini/zz-php.ini /usr/local/etc/php/conf.d/zz-php.ini

FROM dev-local AS dev

COPY composer.json composer.lock ./

RUN composer install --prefer-dist --no-scripts --no-interaction --no-progress --no-cache --optimize-autoloader

RUN --mount=type=bind,target=/context \
    mkdir ./bin/ && \
    cp -a /context/assets/ . && \
    cp -a /context/bin/ . && \
    cp -a /context/config/ . && \
    cp -a /context/migrations/ . && \
    cp -a /context/public/ . && \
    cp -a /context/src/ . && \
    cp -a /context/templates/ . && \
    cp -a /context/tests/ . && \
    cp -a /context/translations/ . && \
    cp -f /context/.env . && \
    cp -f /context/.env.test . && \
    cp -f /context/ecs.php . && \
    cp -f /context/importmap.php . && \
    cp -f /context/phpstan.dist.neon . && \
    cp -f /context/phpstan-baseline.neon . && \
    cp -f /context/phpunit.xml.dist . && \
    cp -f /context/rector.php . && \
    bin/console cache:warmup

FROM base AS prod

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
COPY .env .env.prod ./

RUN bin/console cache:warmup

EXPOSE 9501