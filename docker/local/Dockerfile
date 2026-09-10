FROM node:24-bookworm-slim AS node
FROM composer:2 AS composer
FROM php:8.5-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl gosu make python3 libpq-dev libicu-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install -j$(nproc) pdo_pgsql intl zip mbstring bcmath pcntl \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && git config --system --add safe.directory /var/www/html
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/local/php.ini /usr/local/etc/php/conf.d/99-local.ini
COPY docker/local/entrypoint.sh /usr/local/bin/local-entrypoint
RUN chmod +x /usr/local/bin/local-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["local-entrypoint"]
CMD ["php-fpm", "-F"]
