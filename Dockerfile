
FROM php:8.3-apache

# Extensiones para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN printf 'upload_max_filesize=4M\npost_max_size=5M\nmax_file_uploads=5\nexpose_php=Off\ndisplay_errors=Off\nlog_errors=On\nallow_url_include=Off\nsession.use_strict_mode=1\n' > /usr/local/etc/php/conf.d/jobs-uploads.ini

# Activar módulos de Apache
RUN a2enmod rewrite headers

# Permitir reglas .htaccess
RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/jobs-store.conf \
    && a2enconf jobs-store

# Configurar puerto de Render
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copiar proyecto PHP
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 10000

CMD ["apache2-foreground"]
