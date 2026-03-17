FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    icu-dev \
    linux-headers \
    autoconf \
    g++ \
    make \
    librdkafka-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    intl \
    sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install rdkafka extension
RUN pecl install rdkafka && docker-php-ext-enable rdkafka

# Install opcache
RUN docker-php-ext-install opcache

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/backend

# Copy entrypoint
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
