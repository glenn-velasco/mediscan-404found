#!/bin/sh
set -e

# config:cache/route:cache/view:cache must happen here, not at Docker build
# time - they inline the *current* env() values, and the same image is
# started with different env per environment/role (see infrastructure/README.md).
php artisan storage:link --force
php artisan optimize

exec "$@"
