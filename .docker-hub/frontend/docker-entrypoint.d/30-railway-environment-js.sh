#!/bin/sh
# Railway runtime config for the static frontend.
# - Makes nginx listen on Railway's $PORT (defaults to 3000 for non-Railway use).
# - Generates /app/environment.js (window.environment) from env vars, replacing the
#   placeholder shipped in the image. In Kubernetes this is done via a ConfigMap.
set -e

# Listen on the port Railway assigns (no-op if PORT is unset / already 3000).
if [ -n "${PORT:-}" ]; then
    sed -i "s/listen[[:space:]]\+3000;/listen       ${PORT};/" /etc/nginx/nginx.conf
fi

bool() { [ "$1" = "true" ] && echo true || echo false; }
jsstr() { [ -n "$1" ] && printf "'%s'" "$1" || echo "null"; }

cat > /app/environment.js <<EOF
window.environment = {
  API_ROOT_URL: '${API_ROOT_URL:-/api}',
  COOKIE_PREFIX: '${COOKIE_PREFIX:-ecamp_}',
  PRINT_URL: '${PRINT_URL:-/print}',
  SENTRY_FRONTEND_DSN: $(jsstr "${SENTRY_FRONTEND_DSN:-}"),
  SENTRY_ENVIRONMENT: '${SENTRY_ENVIRONMENT:-production}',
  VERSION: '${VERSION:-}',
  VERSION_LINK_TEMPLATE: '${VERSION_LINK_TEMPLATE:-}',
  TERMS_OF_SERVICE_LINK_TEMPLATE: '${TERMS_OF_SERVICE_LINK_TEMPLATE:-}',
  NEWS_LINK: '${NEWS_LINK:-}',
  HELP_LINK: '${HELP_LINK:-}',
  RECAPTCHA_SITE_KEY: $(jsstr "${RECAPTCHA_SITE_KEY:-}"),
  FEATURE_DEVELOPER: $(bool "${FEATURE_DEVELOPER:-false}"),
  FEATURE_CHECKLIST: $(bool "${FEATURE_CHECKLIST:-false}"),
  LOGIN_INFO_TEXT_KEY: '${LOGIN_INFO_TEXT_KEY:-prod}',
}
EOF
