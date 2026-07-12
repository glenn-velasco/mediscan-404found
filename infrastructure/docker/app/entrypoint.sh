#!/bin/sh
set -e

# depends_on's service_healthy gate only applies to the initial `docker
# compose up` - the Docker engine's own `restart: unless-stopped` policy
# doesn't re-check it, so a container restarting on its own right after a
# Redis blip can race its DNS entry. Wait here too, since this runs on
# every start, not just the first.
php -r '
    $host = getenv("REDIS_HOST") ?: "127.0.0.1";
    $port = (int) (getenv("REDIS_PORT") ?: 6379);
    for ($i = 0; $i < 30; $i++) {
        try {
            $redis = new Redis();
            if (@$redis->connect($host, $port, 1.0)) {
                exit(0);
            }
        } catch (\Throwable $e) {
            // DNS not resolvable yet or connection refused - retry below.
        }
        sleep(1);
    }
    fwrite(STDERR, "Could not connect to Redis at {$host}:{$port} after 30 attempts\n");
    exit(1);
'

# config:cache/route:cache/view:cache must happen here, not at Docker build
# time - they inline the *current* env() values, and the same image is
# started with different env per environment/role (see infrastructure/README.md).
php artisan storage:link --force
php artisan optimize

exec "$@"
