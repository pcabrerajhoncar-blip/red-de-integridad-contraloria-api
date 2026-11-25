FROM php:8.4-apache

# Extensiones necesarias para Laravel y las consultas HTTP a la Contraloría.


RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libcurl4-openssl-dev libonig-dev libzip-dev \
    && docker-php-ext-install curl mbstring zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*



COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


WORKDIR /var/www/html

COPY . .

# Crear directorios necesarios para Laravel.
RUN mkdir -p bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    && chmod -R 775 bootstrap/cache storage

# Instalar dependencias PHP de producción.
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader


# Apache debe servir la carpeta public de Laravel.
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
      /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\\nAllowOverride All\\nRequire all granted\\n</Directory>\\n' \
      > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

# Render proporciona PORT (normalmente 10000). Apache escucha en ese puerto.
CMD ["sh", "-c", "PORT=${PORT:-10000}; sed -i 's/^Listen 80$/Listen '\"$PORT\"'/' /etc/apache2/ports.conf; sed -i 's/<VirtualHost \\*:80>/<VirtualHost *:'\"$PORT\"'>/' /etc/apache2/sites-available/000-default.conf; php artisan config:cache; exec apache2-foreground"]
