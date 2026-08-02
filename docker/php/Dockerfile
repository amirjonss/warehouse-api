FROM php:8.3.3-fpm

RUN apt-get update && apt-get install -y \
        cron \
        git \
        libzip-dev \
        zip \
        unzip \
        # - for pdo_pgsql
        libpq-dev \
        # - for soap
        # libxml2-dev \
        # for GD
        libwebp-dev \
        libsodium-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libfreetype6-dev \
        # for intld
        libicu-dev \
    && docker-php-ext-configure gd \
            --with-webp \
            --with-jpeg \
            --with-freetype \
    && docker-php-ext-configure intl \
	&& docker-php-ext-install \
	     zip \
	     bcmath \
         sodium \
	     gd \
	     #mysqli \
	     #pdo_mysql \
	     intl \
         pdo \
         opcache \
         pgsql \
	     pdo_pgsql \
	     # soap \
	&& curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

## Установка Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Влючение режима debug
RUN echo xdebug.mode=debug > /usr/local/etc/php/conf.d/xdebug.ini
RUN echo xdebug.remote_autostart=1 > /usr/local/etc/php/conf.d/xdebug.ini
RUN echo xdebug.client_host=host.docker.internal. > /usr/local/etc/php/conf.d/xdebug.ini

# for supervisor & redis
RUN apt-get install -y supervisor && pecl install -o -f redis && rm -rf /tmp/pear && docker-php-ext-enable redis

# Git uchun safe.directory qo'shish
RUN git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

COPY cron-file /etc/cron.d/cron-file
COPY run-daemons.sh /run-daemons.sh

ENTRYPOINT /bin/bash /run-daemons.sh

