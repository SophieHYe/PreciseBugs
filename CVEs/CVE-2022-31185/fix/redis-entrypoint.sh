#!/bin/bash
set -eou pipefail

sed -ri -e 's/^bind .*$/bind 0.0.0.0 -::1/g' -e 's/^protected-mode .*$/protected-mode no/g' /etc/redis/redis.conf

exec "$@"
