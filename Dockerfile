FROM php:8.2-apache

# Extensiones que necesita CodeIgniter 4 (curl y mbstring ya vienen incluidas)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip git \
    && docker-php-ext-install intl mysqli pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Apache sirve la carpeta public/ y permite el .htaccess de CodeIgniter
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p writable/cache writable/logs writable/session writable/uploads \
    && chown -R www-data:www-data writable

# Render indica el puerto en la variable PORT. Si existe un Secret File llamado .env,
# se copia a la carpeta del proyecto antes de arrancar Apache.
CMD ["sh", "-c", "if [ -f /etc/secrets/firebase-credentials.json ]; then cp /etc/secrets/firebase-credentials.json /var/www/html/app/Config/firebase-credentials.json; fi; sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
