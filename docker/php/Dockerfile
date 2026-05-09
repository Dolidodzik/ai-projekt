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
RUN addgroup -g ${GID} dev \
    && adduser -D -u ${UID} -G dev -s /bin/bash dev \
    && mkdir -p /var/www/html \
    && chown -R dev:dev /var/www/html

WORKDIR /var/www/html

USER dev

EXPOSE 9000

CMD ["php-fpm", "-F"]
