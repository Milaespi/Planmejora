FROM php:8.2-apache

# Instala Python 3, pip y dependencias del sistema
RUN apt-get update && apt-get install -y \
    python3 python3-pip python3-venv \
    && rm -rf /var/lib/apt/lists/*

# Instala las librerías Python necesarias
RUN pip3 install twilio requests --break-system-packages

# Elimina los módulos MPM conflictivos y deja solo prefork (requerido por PHP)
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
           /etc/apache2/mods-enabled/mpm_event.load \
           /etc/apache2/mods-enabled/mpm_worker.conf \
           /etc/apache2/mods-enabled/mpm_worker.load \
    && a2enmod mpm_prefork rewrite headers

# Copia el proyecto completo al servidor
COPY . /var/www/html/

# Permisos correctos para Apache
RUN chown -R www-data:www-data /var/www/html

# Configuración de Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
