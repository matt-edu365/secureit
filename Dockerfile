FROM php:8.3-apache

RUN a2enmod rewrite headers \
    && mkdir -p /var/www/data /var/www/data/reports \
    && chown -R www-data:www-data /var/www/data

COPY app/ /var/www/html/
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

ENV SECUREIT_APP_NAME="SecureIT" \
    SECUREIT_BASE_URL="https://secureit.ict365.ky" \
    SECUREIT_TENANTS_FILE="/var/www/data/tenants.json" \
    SECUREIT_REPORTS_ROOT="/var/www/data/reports"

EXPOSE 80
