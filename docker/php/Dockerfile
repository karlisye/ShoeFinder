FROM php:8.4-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/shoe-finder.ini
COPY docker/php/entrypoint.sh /usr/local/bin/shoe-finder-entrypoint

RUN chmod +x /usr/local/bin/shoe-finder-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["shoe-finder-entrypoint"]
CMD ["php-fpm"]

FROM base AS development

COPY backend/ /var/www/html/
RUN composer install --no-interaction --prefer-dist

FROM base AS production

COPY docker/php/php.production.ini /usr/local/etc/php/conf.d/zz-shoe-finder-production.ini
COPY backend/ /var/www/html/
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p storage/app/private/feed-imports storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
