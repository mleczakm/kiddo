FROM php:8.4-cli-alpine AS dev-local

WORKDIR /app
ENV APP_ENV=dev

CMD bin/console swoole:server:run


RUN --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions apcu @composer intl ds opcache pcntl pcov pdo_pgsql sysvsem swoole xdebug xsl


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
