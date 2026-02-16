FROM phpmyadmin:5.2.3-fpm

COPY config.inc.php /etc/phpmyadmin/config.user.inc.php

COPY themes/boodark /opt/boodark-theme
COPY entrypoint.sh /usr/local/bin/custom-entrypoint.sh
RUN chmod +x /usr/local/bin/custom-entrypoint.sh

ENTRYPOINT ["custom-entrypoint.sh"]
CMD ["php-fpm"]
