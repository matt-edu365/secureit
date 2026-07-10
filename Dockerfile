FROM php:8.3-apache

RUN a2enmod rewrite headers \
    && mkdir -p /var/www/data /var/www/data/reports \
    && chown -R www-data:www-data /var/www/data

COPY app/ /var/www/html/
COPY shared/ /var/www/shared/
COPY docker/secureit-assets/canonical-controls.json /usr/local/share/secureit/canonical-controls.json
COPY docker/secureit-assets/canonical-controls.version /usr/local/share/secureit/canonical-controls.version
COPY docker/php-secureit.ini /usr/local/etc/php/conf.d/zz-secureit.ini
COPY docker/secureit-entrypoint.sh /usr/local/bin/secureit-entrypoint.sh
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

RUN chmod +x /usr/local/bin/secureit-entrypoint.sh

RUN php -r 'require "/var/www/html/lib.php"; exit((int) (!function_exists("secureit_functional_area_catalog") || !function_exists("secureit_resolve_canonical_area_scores")));'

ENV SECUREIT_APP_NAME="SecureIT" \
    SECUREIT_BASE_URL="https://secureit.ict365.ky" \
    SECUREIT_TENANTS_FILE="/var/www/data/tenants.json" \
    SECUREIT_REPORTS_ROOT="/var/www/data/reports"

ENTRYPOINT ["/usr/local/bin/secureit-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
