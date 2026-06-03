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

ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} dev \
    && adduser -D -u ${UID} -G dev -s /bin/bash dev \
    && mkdir -p /var/www/html \
    && chown -R dev:dev /var/www/html

WORKDIR /var/www/html

USER dev

EXPOSE 9000

COPY --chown=dev:dev start-app.sh /usr/local/bin/start-app.sh
RUN chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
