FROM alpine:latest

RUN apk update && \
    apk add \
        php5-apache2 \
        php5-cli \
        php5-phar \
        php5-json \
        php5-openssl \
        php5-mysqli \
        mysql-client

RUN ln -s /usr/bin/php5 /usr/local/bin/php

RUN wget -O /usr/local/bin/wp \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x /usr/local/bin/wp

RUN mkdir -p /var/www/localhost/htdocs && \
    rm -f /var/www/localhost/htdocs/index.html && \
    cd /var/www/localhost/htdocs && \
    wp core download

COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]
