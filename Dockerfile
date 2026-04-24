FROM php:8.2-apache

RUN a2enmod rewrite

COPY . /var/www/html/

RUN cp -r /var/www/html/data /var/www/html/data-init && \
    mkdir -p /var/www/html/assets/img/productos && \
    cp -r /var/www/html/assets/img/productos /var/www/html/productos-init

RUN chown -R www-data:www-data /var/www/html/

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
