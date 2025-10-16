#!/bin/sh

# Exit immediately if a command exits with a non-zero status.
set -e

# Define the list of variables to be substituted in the msmtprc template.
# This prevents envsubst from substituting other shell variables like $HOME.
export DOLLAR='$'
VARS_TO_SUBSTITUTE='${SMTP_HOST} ${SMTP_PORT} ${SMTP_FROM} ${SMTP_USER} ${SMTP_PASS}'

# Process the template and create the final configuration file.
envsubst "$VARS_TO_SUBSTITUTE" < /etc/msmtprc.template > /etc/msmtprc.tmp

# Conditionally add the 'auth' setting
if [ -n "${SMTP_USER}" ]; then
  echo "auth on" >> /etc/msmtprc.tmp
fi

# Conditionally add TLS settings based on port
if [ "${SMTP_PORT}" = "465" ] || [ "${SMTP_PORT}" = "587" ]; then
  echo "tls on" >> /etc/msmtprc.tmp
  echo "tls_certcheck on" >> /etc/msmtprc.tmp
else
  echo "tls off" >> /etc/msmtprc.tmp
fi

# Move the temporary file to the final location
mv /etc/msmtprc.tmp /etc/msmtprc

# Execute the command passed to this script (the Dockerfile's CMD).
exec "$@"