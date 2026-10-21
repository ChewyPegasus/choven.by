#!/bin/sh
# Healthy when PHP-FPM accepts connections on its port.
exec php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'
