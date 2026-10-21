#!/bin/sh
# Railway start command for the eCamp API (FrankenPHP).
# Writes the JWT keypair from env, then starts the web server.
set -e

cd /app

# lexik/jwt expects the keypair as files (paths configured in .env). On Railway we
# provide them as base64-encoded env vars and materialise them at container start.
if [ -n "${JWT_PRIVATE_KEY_B64:-}" ] && [ -n "${JWT_PUBLIC_KEY_B64:-}" ]; then
    mkdir -p config/jwt
    printf '%s' "$JWT_PRIVATE_KEY_B64" | base64 -d > config/jwt/private.pem
    printf '%s' "$JWT_PUBLIC_KEY_B64" | base64 -d > config/jwt/public.pem
    chmod 644 config/jwt/public.pem
    chmod 600 config/jwt/private.pem
fi

# DB migrations run once per deploy as Railway's preDeployCommand (api/railway.json),
# not here: this script also runs on every wake from sleep, so it must stay fast.

# Hand off to the default FrankenPHP command. SERVER_NAME (=:$PORT) is set via env.
exec frankenphp run --config /etc/frankenphp/Caddyfile
