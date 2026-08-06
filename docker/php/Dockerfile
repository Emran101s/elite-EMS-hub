# syntax=docker/dockerfile:1.7
#
# Application image for staging/production-shaped environments.
# Pins chrome-headless-shell — Browsershot hard-codes headless:'shell',
# which needs that binary (separate from `chrome`). See docs/16-infrastructure.md.

ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm-bookworm

ARG NODE_VERSION=22
# Must match what `npx puppeteer browsers install chrome-headless-shell`
# resolves for the locked puppeteer in package-lock.json (25.4.0 → 151.0.7922.47).
ARG CHROME_HEADLESS_SHELL_VERSION=151.0.7922.47

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PUPPETEER_CACHE_DIR=/var/www/.cache/puppeteer \
    PUPPETEER_SKIP_DOWNLOAD=true

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip ca-certificates \
        libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libicu-dev libonig-dev \
        # chrome-headless-shell runtime deps
        fonts-liberation libasound2 libatk-bridge2.0-0 libatk1.0-0 libcups2 \
        libdbus-1-3 libdrm2 libgbm1 libgtk-3-0 libnspr4 libnss3 libx11-xcb1 \
        libxcomposite1 libxdamage1 libxrandr2 xdg-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql pdo_mysql zip gd intl bcmath pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node for Vite build + puppeteer browser install
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts \
    && npx puppeteer browsers install chrome-headless-shell@${CHROME_HEADLESS_SHELL_VERSION} \
    && npx puppeteer browsers install chrome@${CHROME_HEADLESS_SHELL_VERSION} || true \
    && chown -R www-data:www-data /var/www/.cache

COPY . .
RUN composer dump-autoload --optimize \
    && npm run build \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-elitehub.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-elitehub.conf

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
