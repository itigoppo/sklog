FROM almalinux:9

ENV TZ=Asia/Tokyo

RUN dnf -y update && \
    dnf -y install epel-release && \
    dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm && \
    dnf -y module reset php && \
    dnf -y module enable php:remi-8.3 && \
    dnf -y install \
      httpd \
      php-fpm \
      php-cli \
      php-common \
      php-mbstring \
      php-gd \
      php-curl \
      php-intl \
      php-mysqlnd \
      php-pdo \
      php-xml \
      php-zip \
      php-opcache \
      php-bcmath \
      php-soap \
      php-iconv \
      git \
      unzip \
      zip \
      supervisor \
      msmtp \
    && dnf clean all

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -m -s /bin/bash itigoppo

RUN mkdir -p /home/itigoppo/git/sklog /home/itigoppo/www /run/php-fpm /var/log/php-fpm /var/log/httpd && \
    chown -R itigoppo:itigoppo /home/itigoppo /run/php-fpm /var/log/php-fpm

COPY ./docker/web/httpd.conf /etc/httpd/conf.d/sklog.conf
COPY ./docker/web/php-fpm.conf /etc/php-fpm.d/www.conf
COPY ./docker/web/supervisord.conf /etc/supervisord.conf

RUN sed -i 's/^User apache/User itigoppo/' /etc/httpd/conf/httpd.conf && \
sed -i 's/^Group apache/Group itigoppo/' /etc/httpd/conf/httpd.conf

RUN dnf -y install mod_ssl openssl && dnf clean all

RUN mkdir -p /etc/httpd/ssl && \
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/httpd/ssl/localhost.key \
    -out /etc/httpd/ssl/localhost.crt \
    -subj "/C=JP/ST=Tokyo/L=Tokyo/O=LocalDev/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

RUN rm -f /etc/httpd/conf.d/ssl.conf
COPY ./docker/web/ssl.conf /etc/httpd/conf.d/ssl-sklog.conf

RUN rm -f /etc/httpd/conf.d/welcome.conf /etc/httpd/conf.d/php.conf

RUN echo "date.timezone = Asia/Tokyo" >> /etc/php.ini && \
    echo "mbstring.language = Japanese" >> /etc/php.ini && \
    echo "memory_limit = 256M" >> /etc/php.ini && \
    echo "upload_max_filesize = 50M" >> /etc/php.ini && \
    echo "post_max_size = 50M" >> /etc/php.ini

WORKDIR /home/itigoppo/git/sklog/src

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
