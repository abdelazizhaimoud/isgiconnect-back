# Use the official PHP 8.2 FPM image on Alpine Linux
FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies for Nginx, Supervisor, Composer, and netcat
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    pcre-dev \
    make g++ \
    netcat-openbsd \
    linux-headers

# Install required PHP extensions for Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql bcmath sockets pcntl zip opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Copy Nginx and Supervisor configurations
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

# Copy startup script and make it executable
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80 for Nginx
EXPOSE 80

# Set the entrypoint to our startup script
ENTRYPOINT ["/usr/local/bin/start.sh"]

