FROM php:8.4-fpm-bookworm

# ビルド依存と Laravel に必要な拡張
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev libonig-dev \
        zlib1g-dev autoconf pkg-config build-essential libcurl4-openssl-dev libssl-dev \
 && docker-php-ext-install pdo pdo_pgsql intl zip mbstring opcache bcmath \
 && rm -rf /var/lib/apt/lists/*

# PECL: protobuf + opentelemetry
RUN pecl install protobuf && docker-php-ext-enable protobuf
RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry

# Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
