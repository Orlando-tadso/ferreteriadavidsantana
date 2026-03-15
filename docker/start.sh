#!/usr/bin/env sh
set -eu

PORT_VALUE="${PORT:-80}"

# Railway may provide a dynamic port. Update Apache to listen on it.
sed -ri "s/Listen 80/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
