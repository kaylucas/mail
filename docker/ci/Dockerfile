FROM php:8.4-cli-bookworm

# Install system dependencies including build tools for PECL
RUN apt-get update && apt-get install -y \
    $PHPIZE_DEPS \
    gettext-base \
    libzip-dev \
    git \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    curl \
    gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd bcmath mbstring exif pcntl \
    && docker-php-ext-enable pdo_mysql zip gd bcmath mbstring exif pcntl

# Install Xdebug for code coverage via PECL
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Keep build dependencies in CI image for potential additional builds

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install pnpm
RUN npm install -g pnpm@10.12.3

# Set working directory
WORKDIR /var/www/html
