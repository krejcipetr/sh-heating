FROM php:5-apache
COPY . /usr/src/sh-heating
RUN docker-php-ext-install sysvsem  \
	&& apt-get update && apt-get install -y libdbus-1-dev libxml2-dev  \
	&& pecl install "channel://pecl.php.net/dbus-0.1.1" \
	&& docker-php-ext-enable dbus
	&& echo "Alias 
WORKDIR /usr/src/sh-heating
VOLUME  /usr/src/sh-heating/state
CMD php ./daemon/heating_control.php 
