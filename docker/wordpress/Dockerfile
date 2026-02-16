FROM wordpress:php8.5-fpm

# ── Match www-data UID/GID to the host user so bind-mount files are
#    writable by both PHP-FPM and the host without permission drift. ──
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN groupmod -g ${HOST_GID} www-data 2>/dev/null || true \
    && usermod -u ${HOST_UID} -g ${HOST_GID} www-data 2>/dev/null || true \
    && find /var/www -user 33 -exec chown -h ${HOST_UID}:${HOST_GID} {} + 2>/dev/null || true

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client git unzip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── OPcache tuning ───────────────────────────────────────────────────
RUN echo '\
opcache.revalidate_freq=60\n\
opcache.interned_strings_buffer=16\n\
opcache.max_accelerated_files=10000\n\
' > /usr/local/etc/php/conf.d/opcache-tuning.ini

COPY entrypoint.sh /usr/local/bin/custom-entrypoint.sh
RUN chmod +x /usr/local/bin/custom-entrypoint.sh

ENTRYPOINT ["custom-entrypoint.sh"]
CMD ["php-fpm"]
