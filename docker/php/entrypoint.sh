#!/bin/sh

# Exit immediately if a command exits with a non-zero status.
set -e

# Execute the command passed to this script (the Dockerfile's CMD).
exec "$@"
