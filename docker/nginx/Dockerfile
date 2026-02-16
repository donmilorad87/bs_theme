FROM nginx:latest AS builder

# Install build dependencies for brotli + zstd dynamic modules
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git gcc make libbrotli-dev libpcre2-dev libssl-dev zlib1g-dev libzstd-dev \
    && rm -rf /var/lib/apt/lists/*

# Fetch nginx source matching the installed version
RUN NGINX_VERSION=$(nginx -v 2>&1 | sed 's/.*\///') \
    && curl -fsSL "https://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -o /tmp/nginx.tar.gz \
    && mkdir -p /usr/src/nginx \
    && tar -xzf /tmp/nginx.tar.gz -C /usr/src/nginx --strip-components=1 \
    && rm /tmp/nginx.tar.gz

# Clone ngx_brotli module (with bundled brotli encoder/decoder)
RUN git clone --recurse-submodules --depth 1 https://github.com/google/ngx_brotli.git /usr/src/ngx_brotli

# Clone zstd-nginx-module
RUN git clone --depth 1 https://github.com/tokers/zstd-nginx-module.git /usr/src/zstd-nginx-module

# Build both as dynamic modules against the existing nginx binary's configure flags
RUN cd /usr/src/nginx \
    && NGINX_ARGS=$(nginx -V 2>&1 | grep 'configure arguments:' | sed 's/configure arguments: //') \
    && eval ./configure ${NGINX_ARGS} \
       --add-dynamic-module=/usr/src/ngx_brotli \
       --add-dynamic-module=/usr/src/zstd-nginx-module \
    && make modules -j$(nproc) \
    && cp objs/ngx_http_brotli_filter_module.so /usr/lib/nginx/modules/ \
    && cp objs/ngx_http_brotli_static_module.so /usr/lib/nginx/modules/ \
    && cp objs/ngx_http_zstd_filter_module.so /usr/lib/nginx/modules/ \
    && cp objs/ngx_http_zstd_static_module.so /usr/lib/nginx/modules/

# ── Final image ──────────────────────────────────────────────────────
FROM nginx:latest

# Install runtime libraries for brotli + zstd
RUN apt-get update \
    && apt-get install -y --no-install-recommends libbrotli1 libzstd1 \
    && rm -rf /var/lib/apt/lists/*

# Copy compiled modules from builder
COPY --from=builder /usr/lib/nginx/modules/ngx_http_brotli_filter_module.so /usr/lib/nginx/modules/
COPY --from=builder /usr/lib/nginx/modules/ngx_http_brotli_static_module.so /usr/lib/nginx/modules/
COPY --from=builder /usr/lib/nginx/modules/ngx_http_zstd_filter_module.so /usr/lib/nginx/modules/
COPY --from=builder /usr/lib/nginx/modules/ngx_http_zstd_static_module.so /usr/lib/nginx/modules/

# Load modules in main nginx.conf (before http block)
RUN sed -i '1i load_module modules/ngx_http_brotli_filter_module.so;\nload_module modules/ngx_http_brotli_static_module.so;\nload_module modules/ngx_http_zstd_filter_module.so;\nload_module modules/ngx_http_zstd_static_module.so;' /etc/nginx/nginx.conf

# Run nginx workers as www-data so cache files match PHP's user.
# Both containers share the nginx_cache volume; same owner = easy purge.
RUN sed -i 's/^user  nginx;/user  www-data;/' /etc/nginx/nginx.conf

RUN rm /etc/nginx/conf.d/default.conf

COPY default.conf /etc/nginx/conf.d/default.conf
COPY certs/ /etc/nginx/certs/
