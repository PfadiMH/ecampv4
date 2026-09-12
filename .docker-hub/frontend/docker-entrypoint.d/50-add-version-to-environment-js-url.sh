#!/bin/sh
set -e

runtime_version="${VERSION:-${RAILWAY_DEPLOYMENT_ID:-}}"

if [ -n "$runtime_version" ]; then
  sed -i "s|<script src=\"/environment.js\"|<script src=\"/environment.js?version=$runtime_version\"|" /app/index.html
fi
