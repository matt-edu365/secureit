FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" dom gd mbstring \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && mkdir -p /var/www/data /var/www/data/reports \
    && chown -R www-data:www-data /var/www/data

COPY --from=vendor /app/vendor/ /var/www/vendor/
COPY Logo_1.png /var/www/Logo_1.png
COPY app/ /var/www/html/
COPY shared/ /var/www/shared/
COPY docker/secureit-assets/canonical-controls.json /usr/local/share/secureit/canonical-controls.json
COPY docker/secureit-assets/canonical-controls.version /usr/local/share/secureit/canonical-controls.version
COPY docker/php-secureit.ini /usr/local/etc/php/conf.d/zz-secureit.ini
COPY docker/secureit-entrypoint.sh /usr/local/bin/secureit-entrypoint.sh
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

RUN chmod +x /usr/local/bin/secureit-entrypoint.sh

RUN php -r 'require "/var/www/vendor/autoload.php"; require "/var/www/html/lib.php"; require "/var/www/shared/report-pdf.php"; exit((int) (!class_exists("Dompdf\\Dompdf") || !function_exists("secureit_functional_area_catalog") || !function_exists("secureit_resolve_canonical_area_scores") || !function_exists("secureit_report_render_pdf")));'

ENV SECUREIT_APP_NAME="SecureIT" \
    SECUREIT_BASE_URL="https://secureit.ict365.ky" \
    SECUREIT_TENANTS_FILE="/var/www/data/tenants.json" \
    SECUREIT_REPORTS_ROOT="/var/www/data/reports"

ENTRYPOINT ["/usr/local/bin/secureit-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
