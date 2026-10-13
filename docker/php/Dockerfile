FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY docker/php/zzz-laravel.conf /usr/local/etc/php-fpm.d/zzz-laravel.conf
COPY docker/php/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Node + npm so `npm run build` / `npm run test` run inside the app container. The Debian
# (glibc) node image is used - the alpine one is musl and won't run on this base. This is
# the only Node in the stack. Kept above the
# unpinned composer:latest so a new composer release doesn't rebuild these layers.
COPY --from=node:24.18-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24.18-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["/entrypoint.sh"]
