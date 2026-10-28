FROM php:8.5-fpm-alpine

RUN apk add --no-cache openssl \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS openssl-dev \
    && pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb \
    && apk del .build-deps

WORKDIR /app

COPY app/ ./

EXPOSE 9000
CMD ["php-fpm"]
