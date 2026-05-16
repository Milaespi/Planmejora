FROM php:8.2-apache

# Instala Python 3 y pip sin actualizar paquetes ya instalados
RUN apt-get update && apt-get install -y --no-upgrade --no-install-recommends \
    python3 python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Instala las librerías Python necesarias
RUN pip3 install twilio requests --break-system-packages

# Habilita mod_rewrite y headers
RUN a2enmod rewrite headers

# Copia el proyecto completo al servidor
COPY . /var/www/html/

# Permisos correctos para Apache
RUN chown -R www-data:www-data /var/www/html

# Configuración de Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Script de arranque: corrige el MPM en tiempo de ejecución antes de iniciar Apache
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
