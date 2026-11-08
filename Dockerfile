FROM php:8.1-apache

RUN { echo 'upload_max_filesize=5M'; echo 'post_max_size=16M'; echo 'memory_limit=256M'; } > /usr/local/etc/php/conf.d/uploads.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY start.sh /var/www/html/start.sh
RUN chmod +x start.sh

EXPOSE 80

CMD ["/bin/bash", "start.sh"]
