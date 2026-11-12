
FROM php:8.3-apache

# Extensiones para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

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
