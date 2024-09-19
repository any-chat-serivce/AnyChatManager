ARG VERSION
FROM php:${VERSION}-fpm

RUN apt-get update && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev git unzip libicu-dev libzstd-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j$(nproc) gd zip pcntl bcmath intl \
    && yes | pecl install igbinary\
    && docker-php-ext-enable opcache igbinary \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

ENV PATH /var/www/html:$PATH
