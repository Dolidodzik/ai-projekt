FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash \
        git \
        curl \
        unzip \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        postgresql-dev \
        autoconf \
        g++ \
        make \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        bcmath \
        mbstring \
        opcache \
    && apk del autoconf g++ make linux-headers \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { \
        echo "upload_max_filesize=50M"; \
        echo "post_max_size=50M"; \
        echo "max_file_uploads=20"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

ARG UID=1000
ARG GID=1000
RUN if ! getent group ${GID} >/dev/null; then addgroup -g ${GID} dev; fi \
    && if ! getent passwd ${UID} >/dev/null; then adduser -D -u ${UID} -G "$(getent group ${GID} | cut -d: -f1)" -s /bin/bash dev; fi \
    && mkdir -p /var/www/html \
    && chown -R ${UID}:${GID} /var/www/html

WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

USER ${UID}:${GID}

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm", "-F"]
