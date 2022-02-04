FROM php:7.2

RUN pecl install xdebug-2.7.2 \
    && docker-php-ext-enable xdebug

RUN apt-get update \
 && apt-get install -y git wget tar

WORKDIR /usr/bin

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');"

WORKDIR /tmp
RUN wget https://s3.amazonaws.com/mountebank/v2.4/mountebank-v2.4.0-linux-x64.tar.gz \
    && tar -xf mountebank-v2.4.0-linux-x64.tar.gz \
    && cp -r mountebank-v2.4.0-linux-x64/* /usr/bin/ \
    && rm -rf mountebank-v2.4.0-linux-x64*

RUN mkdir /src
COPY . /src

WORKDIR /src
RUN composer.phar install
