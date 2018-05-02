#!/bin/sh

set -eu

cd /var/www/localhost/htdocs

while ! mysql -hdb -uroot -pwordpress -e 'select 1;' >/dev/null; do
    sleep 1
done

wp config create \
    --dbhost=db \
    --dbname=wordpress \
    --dbuser=wordpress \
    --dbpass=wordpress

wp core install \
    --url=http://phastpress.test \
    --title=PhastPress \
    --admin_user=admin \
    --admin_email=info@kiboit.com \
    --admin_password=admin

ln -s /data wp-content/plugins/phastpress
wp plugin activate phastpress

cd /
mkdir -p /run/apache2
exec httpd -D FOREGROUND -f /etc/apache2/httpd.conf
