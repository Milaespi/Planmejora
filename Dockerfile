FROM php:8.2-apache

# Instala Python 3, pip y dependencias del sistema
RUN apt-get update && apt-get install -y \
    python3 python3-pip python3-venv \
    && rm -rf /var/lib/apt/lists/*

# Instala las librerías Python necesarias
RUN pip3 install twilio requests --break-system-packages

# Habilita mod_rewrite de Apache
RUN a2enmod rewrite headers

# Copia el proyecto completo al servidor
COPY . /var/www/html/

# Permisos correctos para Apache
RUN chown -R www-data:www-data /var/www/html

# Configuración de Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
