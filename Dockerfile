FROM php:7.2

RUN pecl install xdebug-2.7.2 \
    && docker-php-ext-enable xdebug

RUN apt-get update \
 && apt-get install -y git wget tar

WORKDIR /usr/bin

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');"

WORKDIR /tmp
RUN wget https://s3.amazonaws.com/mountebank/v2.0/mountebank-v2.0.0-linux-x64.tar.gz \
    && tar -xf mountebank-v2.0.0-linux-x64.tar.gz \
    && cp -r mountebank-v2.0.0-linux-x64/* /usr/bin/ \
    && rm -rf mountebank-v2.0.0-linux-x64*

RUN mkdir /src
COPY . /src

WORKDIR /src
RUN composer.phar install
