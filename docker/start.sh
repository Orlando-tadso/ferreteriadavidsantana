#!/usr/bin/env sh
set -eu

PORT_VALUE="${PORT:-80}"

# Prevent Apache from loading multiple MPMs in some container runtime scenarios.
a2dismod mpm_event >/dev/null 2>&1 || true
a2dismod mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

# Railway may provide a dynamic port. Update Apache to listen on it.
sed -ri "s/Listen 80/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
