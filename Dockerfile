FROM php:latest

# copy the Composer PHAR from the Composer image into the PHP image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install unzip utility and libs needed by zip PHP extension
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip

RUN docker-php-ext-install zip

ADD php.ini /usr/local/etc/php/

COPY . /app

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer pre-install

CMD ["composer", "start"]
