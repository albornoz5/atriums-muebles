#!/bin/bash
set -e

# Inicializar data si no existe
if [ ! -f /var/www/html/data/productos.json ]; then
    cp -r /var/www/html/data-init/. /var/www/html/data/
fi

# Siempre actualizar productos.json y config.json desde el build
cp /var/www/html/data-init/productos.json /var/www/html/data/productos.json
if [ -f /var/www/html/data-init/config.json ]; then
    cp /var/www/html/data-init/config.json /var/www/html/data/config.json
fi

# Crear directorio de imágenes dentro del volumen data
mkdir -p /var/www/html/data/img
chmod -R 777 /var/www/html/data/img

exec "$@"
