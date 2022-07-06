FROM php:7.4-fpm

WORKDIR /var/www

# install the jq library to support the snowflake build
RUN apt-get -y update \
    && apt-get -y install --no-install-recommends \
    supervisor \
    g++ \
    bash \
    httpie \
    unzip \
    libzip-dev \
    autoconf \
    make \
    jq \
    git \
    libicu-dev  \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo_mysql pcntl bcmath zip \
    && docker-php-ext-enable intl

# clone pdo_snowflake repo. TODO version?
RUN mkdir /tmp/snowflake \
    && git clone https://github.com/snowflakedb/pdo_snowflake.git /tmp/snowflake

# run the compile command
# move the compiled lib and cert to extensions dir
RUN export PHP_HOME=/usr/local \
    && bash /tmp/snowflake/scripts/build_pdo_snowflake.sh \
    && cp /tmp/snowflake/libsnowflakeclient/cacert.pem /tmp/snowflake/modules/pdo_snowflake.so /usr/local/lib/php/extensions/no-debug-non-zts-*/ \
    && rm -rf /tmp/snowflake

COPY docker/local/api/pdo_snowflake.ini /usr/local/etc/php/conf.d/pdo_snowflake.ini

RUN pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

ARG INSTALL_XDEBUG
ARG REMOTE_HOST_IP

RUN if [ "$INSTALL_XDEBUG" = "true" ];  \
    then \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
fi

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY docker/local/api/php.ini $PHP_INI_DIR/php.ini

COPY docker/local/api/www.conf $PHP_INI_DIR/../php-fpm.d/www.conf

# Composer dependencies always cached unless changed
RUN mkdir tests /tmp/packages

COPY composer.* /tmp/packages

# Install dependencies, but don't run scripts or init autoloaders as the app is missing
RUN cd /tmp/packages \
    && composer install --no-scripts --no-autoloader

# copy to the rest of the app
# run normal composer (with scripts) - all deps are cached already
RUN mv /tmp/packages/* . \
    && composer install

COPY .env.example .env

COPY docker/local/api/entrypoint.sh /usr/local/bin

RUN ["chmod", "+x", "/usr/local/bin/entrypoint.sh"]

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
