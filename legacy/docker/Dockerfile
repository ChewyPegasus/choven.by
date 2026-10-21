FROM php:8.2-fpm

# Installation of system dependencies
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

# rdkafka installation
RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

# Composer installation
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuring Git to work with mounted directories
RUN git config --global --add safe.directory /var/www

# Creating www-data user with proper access rights
RUN usermod -u 1000 www-data && \
    groupmod -g 1000 www-data

# Configuring working directory
WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]