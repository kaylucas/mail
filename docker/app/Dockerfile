# Stage 1: Node builder for frontend assets
FROM node:20-bookworm-slim AS node-builder

WORKDIR /build

# Install pnpm
RUN npm install -g pnpm@10.12.3

# Copy package files
COPY package.json pnpm-lock.yaml ./

# Install Node dependencies
RUN pnpm install --frozen-lockfile

# Copy application source needed for build
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public

# Build frontend assets
RUN pnpm run build

# Remove Vite hot file if it exists (tells Laravel to use dev server)
RUN rm -f /build/public/hot

# Stage 2: PHP production image
FROM php:8.4-apache-bookworm

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
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd bcmath mbstring exif pcntl \
    && docker-php-ext-enable pdo_mysql zip gd bcmath mbstring exif pcntl

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Clean up build dependencies to reduce image size
RUN apt-get purge -y --auto-remove $PHPIZE_DEPS

# Optionally install Xdebug for development (comment out for production)
# RUN pecl install xdebug \
#     && docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache DocumentRoot and Directory settings
RUN sed -i 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && echo '\n<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Copy custom PHP configuration
COPY docker/config/php.ini /usr/local/etc/php/conf.d/50-custom-php.ini

# Copy development entrypoint script
COPY docker/app/docker-entrypoint-dev.sh /usr/local/bin/docker-entrypoint-dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-dev.sh

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Copy built frontend assets from node-builder stage
COPY --from=node-builder /build/public/build ./public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set ownership and permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
