#!/bin/bash
set -e

# Si el volumen de data está vacío, inicializarlo con los datos del build
if [ ! -f /var/www/html/data/productos.json ]; then
    cp -r /var/www/html/data-init/. /var/www/html/data/
    chown -R www-data:www-data /var/www/html/data
fi

# Si el volumen de fotos está vacío, copiar las fotos iniciales
if [ -z "$(ls -A /var/www/html/assets/img/productos 2>/dev/null)" ]; then
    cp -r /var/www/html/productos-init/. /var/www/html/assets/img/productos/
    chown -R www-data:www-data /var/www/html/assets/img/productos
fi

# Asegurar permisos de escritura en el volumen de imágenes siempre
chown -R www-data:www-data /var/www/html/assets/img/productos 2>/dev/null || true
chmod -R 775 /var/www/html/assets/img/productos 2>/dev/null || true

exec "$@"
