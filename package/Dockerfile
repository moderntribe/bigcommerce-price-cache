FROM phpdockerio/php56-fpm:latest
MAINTAINER BigCommerce

# Environments
ENV TIMEZONE            Etc/UTC
ENV PHP_MEMORY_LIMIT    512M
ENV MAX_UPLOAD          50M
ENV PHP_MAX_FILE_UPLOAD 200
ENV PHP_MAX_POST        100M
ENV NVM_DIR             /usr/local/nvm
ENV NVM_VERSION         0.33.8
ENV COMPOSER_VERSION    1.6.1

# Install selected extensions and other stuff
RUN apt-get update \
    && apt-get -y --no-install-recommends install \
       git bash curl openssl rsync bzip2 \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && chmod +x /usr/local/bin/composer

WORKDIR /data
