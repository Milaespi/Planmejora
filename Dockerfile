FROM php:8.2-apache

# --no-upgrade evita que apt actualice apache2 y resetee la configuración MPM
# --no-install-recommends evita instalar paquetes innecesarios
RUN apt-get update && apt-get install -y --no-upgrade --no-install-recommends \
    python3 python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Instala las librerías Python necesarias
RUN pip3 install twilio requests --break-system-packages

# Habilita mod_rewrite y headers (mpm_prefork ya está activo en la imagen base)
RUN a2enmod rewrite headers

# Copia el proyecto completo al servidor
COPY . /var/www/html/

# Permisos correctos para Apache
RUN chown -R www-data:www-data /var/www/html

# Configuración de Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
