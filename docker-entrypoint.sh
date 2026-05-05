#!/bin/bash
set -e

# Inicializar data si no existe
if [ ! -f /var/www/html/data/productos.json ]; then
    cp -r /var/www/html/data-init/. /var/www/html/data/
fi

# Siempre actualizar productos.json desde el build
cp /var/www/html/data-init/productos.json /var/www/html/data/productos.json
# config.json solo se copia si NO existe (el admin lo gestiona en el volumen)
if [ ! -f /var/www/html/data/config.json ] && [ -f /var/www/html/data-init/config.json ]; then
    cp /var/www/html/data-init/config.json /var/www/html/data/config.json
fi

# Siempre sincronizar imagenes de productos desde el build al volumen
if [ -d /var/www/html/data-init/img/productos ]; then
    mkdir -p /var/www/html/data/img/productos
    cp -r /var/www/html/data-init/img/productos/. /var/www/html/data/img/productos/
fi

# Permisos para uploads del admin
mkdir -p /var/www/html/data/img
chmod -R 777 /var/www/html/data/img

exec "$@"
