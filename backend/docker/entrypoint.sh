#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    # Fine for a local demo: tokens are hashed in the database, not encrypted with this key.
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
    echo "APP_KEY not set: generated an ephemeral key."
fi

php artisan menumind:bootstrap --wait=90

# Build the search index in the background: the API is usable right away
# (SQL fallback) while Typesense downloads its embedding model on first run.
php artisan menumind:reindex --wait=180 &

exec "$@"
