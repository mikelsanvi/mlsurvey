# Misma base que produccion: Ubuntu 26.04 LTS con los paquetes PHP de Ubuntu
# (mod_php sobre Apache), asi los parches de seguridad llegan por apt igual que
# alli. Reconstruir con "docker compose build --pull" para recogerlos.
FROM ubuntu:26.04

ENV DEBIAN_FRONTEND=noninteractive

# PHP con los mismos modulos que produccion; composer necesita git/unzip para
# descargar los paquetes; openssl y pdftotext (poppler-utils) comprueban la
# firma del extracto de formacion.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 libapache2-mod-php8.5 php8.5-cli php8.5-common \
        php8.5-curl php8.5-intl php8.5-mbstring php8.5-mysql php8.5-readline \
        php8.5-xml php8.5-zip \
        ca-certificates git unzip openssl poppler-utils \
    && rm -rf /var/lib/apt/lists/* \
    && (a2dismod -q mpm_event || true) \
    && a2enmod mpm_prefork rewrite headers \
    && rm -f /var/www/html/index.html \
    && ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log \
    && ln -sf /dev/stdout /var/log/apache2/other_vhosts_access.log

COPY docker/apache-mlsurvey.conf /etc/apache2/conf-available/mlsurvey.conf
COPY docker/php.ini /etc/php/8.5/apache2/conf.d/99-mlsurvey.ini
COPY docker/php.ini /etc/php/8.5/cli/conf.d/99-mlsurvey.ini
RUN a2enconf mlsurvey

COPY docker/apache2-foreground /usr/local/bin/apache2-foreground
RUN chmod +x /usr/local/bin/apache2-foreground

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# PHPMailer (envio de correo) y HugeRTE (editor de descripciones), ver README.
RUN composer require --no-interaction --no-progress --optimize-autoloader \
        phpmailer/phpmailer:^6.9 \
        hugerte/hugerte:^1.0 \
        zoon/php-bounce-handler \
    && chown -R www-data:www-data /var/www/html

COPY --chown=www-data:www-data . /var/www/html/

# files/ guarda los adjuntos de las encuestas, log/ los errores de PHP (ver .htaccess).
RUN mkdir -p /var/www/html/files /var/www/html/log \
    && chown -R www-data:www-data /var/www/html/files /var/www/html/log

COPY docker/entrypoint.sh /usr/local/bin/mlsurvey-entrypoint
RUN chmod +x /usr/local/bin/mlsurvey-entrypoint

EXPOSE 80
ENTRYPOINT ["mlsurvey-entrypoint"]
CMD ["apache2-foreground"]
