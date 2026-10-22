#!/bin/sh
# Healthy when the application answers GET /health with 200 (database reachable).
# Goes through the internal listener on 127.0.0.1:2080 with the host name of DEFAULT_URI, so the request passes
# Symfony's TRUSTED_HOSTS check whatever SERVER_NAME is.
set -e
host=$(printf '%s' "${DEFAULT_URI:-http://localhost}" | sed -E 's#^[a-zA-Z]+://([^/:?]+).*#\1#')
exec curl -fsS -o /dev/null --max-time 4 -H "Host: ${host}" http://127.0.0.1:2080/health
