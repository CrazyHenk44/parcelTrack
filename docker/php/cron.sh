#!/bin/sh
set -e

# Create a cron job that runs every 5 minutes
echo "*/5 * * * * /usr/local/bin/php /opt/parceltrack/src/cron.php >> /var/log/cron.log 2>&1" > /etc/cron.d/parceltrack-cron

# Give execution rights on the cron job
chmod 0644 /etc/cron.d/parceltrack-cron

# Start cron
cron -f