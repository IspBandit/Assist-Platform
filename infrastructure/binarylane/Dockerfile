FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
      fcgi freetype font-dejavu libjpeg-turbo libpng libwebp libzip oniguruma \
    && apk add --no-cache --virtual .build-deps \
      freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd exif opcache \
    && apk del .build-deps

COPY php.ini /usr/local/etc/php/conf.d/assist-platform.ini

WORKDIR /var/www/html
USER www-data
