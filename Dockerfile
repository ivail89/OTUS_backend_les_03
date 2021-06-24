FROM php:7.4-apache
COPY . /var/www/html/

RUN apt-get update && apt-get install -y wget git unzip \
    && pecl install xdebug

RUN docker-php-ext-install sockets
RUN echo "zend_extension = xdebug.so" >> /usr/local/etc/php/php.ini

RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/php.ini
RUN echo "xdebug.start_with_request = yes" >> /usr/local/etc/php/php.ini
RUN echo "xdebug.discover_client_host = 1" >> /usr/local/etc/php/php.ini
RUN echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/php.ini
RUN echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/php.ini
