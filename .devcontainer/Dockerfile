FROM php:8.0-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Install XDebug via PECL and enable it
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy XDebug ini configuration
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Install Composer (official image binary)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /workspaces/storage
