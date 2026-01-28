FROM php:8.4-fpm

# Instalar dependências do sistema e extensões PHP
RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        zip \
        unzip \
        curl \
        git \
        nano \
        vim \
        libzip-dev \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libxml2-dev \
        libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        mbstring \
        zip \
        opcache \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Configurar Xdebug
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir diretório de trabalho
WORKDIR /var/www/html

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html

# Configurar usuário não-root
USER www-data

EXPOSE 9000

CMD ["php-fpm"]