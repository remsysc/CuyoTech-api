# Multi-stage Dockerfile for CuyoTech API
FROM docker.io/library/composer:2 AS composer

FROM docker.io/library/php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    dos2unix \
    git \
    icu-dev \
    libzip-dev \
    postgresql-client \
    postgresql-dev \
    sqlite \
    sqlite-dev \
    unzip \
    zip

# Install PHP extensions required by Laravel
RUN docker-php-ext-install \
    bcmath \
    pcntl \
    pdo_pgsql \
    pgsql \
    zip

# Copy Composer binary from official Composer image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Configure safe git directory
RUN git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

# Copy application code
COPY . .

# Ensure entrypoint is executable and standard storage structure exists
RUN dos2unix /var/www/html/docker/entrypoint.sh 2>/dev/null || sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh \
    && chmod +x /var/www/html/docker/entrypoint.sh \
    && cp /var/www/html/docker/entrypoint.sh /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
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

ENTRYPOINT ["/bin/bash", "-c", "sed -i 's/\\r$//' /var/www/html/docker/entrypoint.sh 2>/dev/null; chmod +x /var/www/html/docker/entrypoint.sh 2>/dev/null; exec /bin/bash /var/www/html/docker/entrypoint.sh \"$@\"", "--"]
