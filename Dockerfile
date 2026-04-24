FROM php:8.2-apache

RUN a2enmod rewrite && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf && \
    echo "upload_max_filesize=256M\npost_max_size=256M\nmemory_limit=256M\nmax_execution_time=300" > /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html/

RUN cp -r /var/www/html/data /var/www/html/data-init && \
    mkdir -p /var/www/html/assets/img/productos && \
    cp -r /var/www/html/assets/img/productos /var/www/html/productos-init

RUN chown -R www-data:www-data /var/www/html/

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
