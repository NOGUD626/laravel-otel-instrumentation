FROM nginx:1.27-bookworm

# nginx 公式 deb リポジトリを明示追加 (mainline。nginx-module-otel が入っている)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        curl gnupg2 ca-certificates lsb-release \
 && curl -fsSL https://nginx.org/keys/nginx_signing.key \
      | gpg --dearmor -o /usr/share/keyrings/nginx-archive-keyring.gpg \
 && echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] http://nginx.org/packages/mainline/debian bookworm nginx" \
      > /etc/apt/sources.list.d/nginx.list \
 && printf "Package: *\nPin: origin nginx.org\nPin-Priority: 900\n" \
      > /etc/apt/preferences.d/99nginx \
 && apt-get update \
 && apt-get install -y --no-install-recommends nginx-module-otel \
 && rm -rf /var/lib/apt/lists/*
