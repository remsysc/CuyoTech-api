# Multi-stage Dockerfile for CuyoTech API
FROM docker.io/library/composer:2 AS composer

FROM docker.io/library/php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    sqlite \
    sqlite-dev \
    unzip \
    zip

# Install PHP extensions required by Laravel
RUN docker-php-ext-install \
    bcmath \
    pcntl \
    zip

# Copy Composer binary from official Composer image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Configure safe git directory
RUN git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

# Copy application code
COPY . .

# Ensure entrypoint is executable and standard storage structure exists
RUN chmod +x /var/www/html/docker/entrypoint.sh \
    && mkdir -p \
        database \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache 2>/dev/null || true \
    && chmod 777 database 2>/dev/null || true

EXPOSE 8000

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
