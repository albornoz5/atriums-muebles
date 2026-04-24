#!/bin/bash
set -e

# Si el volumen de data está vacío, inicializarlo con los datos del build
if [ ! -f /var/www/html/data/productos.json ]; then
    cp -r /var/www/html/data-init/. /var/www/html/data/
fi

# Crear directorio de imágenes dentro del volumen data
mkdir -p /var/www/html/data/img
chmod -R 777 /var/www/html/data/img

exec "$@"
