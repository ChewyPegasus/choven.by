FROM php:8.2-fpm

# Установка системных зависимостей, включая acl для прав доступа
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    librdkafka-dev \
    libicu-dev \
    acl \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    intl

# Установка расширения rdkafka
RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка Git для работы со смонтированными томами
RUN git config --global --add safe.directory /var/www

# Настройка рабочей директории
WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]