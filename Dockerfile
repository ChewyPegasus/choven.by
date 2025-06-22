FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    librdkafka-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip

# Установка расширения rdkafka
RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка рабочей директории
WORKDIR /var/www

# Копирование проекта
COPY . /var/www

# Установка зависимостей Composer, включая dev-зависимости
RUN composer install --optimize-autoloader

# Права на директории
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]