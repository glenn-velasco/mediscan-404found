FROM nginx:1.27-alpine

COPY nginx.conf /etc/nginx/nginx.conf

# Rendered to /etc/nginx/conf.d/*.conf at container start by the base
# image's entrypoint (envsubst on ${NGINX_APP_HOST}/etc.), using whichever
# environment's own hostnames docker-compose.{staging,production}.yml sets -
# keeps a staging container from ever answering for production's hostnames
# (or vice versa) even though both stacks share this one image and bind the
# same host ports.
COPY templates/ /etc/nginx/templates/

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/nginx-health || exit 1
