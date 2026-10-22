# Mock OpenID Connect provider for the Railway *staging* environment only.
# Staging can't use the real Pfadi SSO (its OIDC client only knows camp.pfadimh.ch),
# so QA logs in as one of the seeded dev users from infra/mock-oidc/login.html.
# Never point production at this service.
FROM ghcr.io/navikt/mock-oauth2-server:2.3.0
COPY infra/mock-oidc/ /app/config/
ENV JSON_CONFIG_PATH=/app/config/config.json
ENV SERVER_PORT=8080
