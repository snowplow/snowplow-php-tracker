FROM php:7.2

RUN pecl install xdebug-2.7.2 \
    && docker-php-ext-enable xdebug

RUN apt-get update \
 && apt-get install -y git wget tar

ADD ./script/install-composer.sh /script/install-composer.sh
RUN chmod +x /script/install-composer.sh

WORKDIR /usr/bin
RUN /script/install-composer.sh

WORKDIR /tmp
RUN wget https://s3.amazonaws.com/mountebank/v2.4/mountebank-v2.4.0-linux-x64.tar.gz \
    && tar -xf mountebank-v2.4.0-linux-x64.tar.gz \
    && cp -r mountebank-v2.4.0-linux-x64/* /usr/bin/ \
    && rm -rf mountebank-v2.4.0-linux-x64*

RUN mkdir /src
COPY . /src

WORKDIR /src
RUN composer.phar install
