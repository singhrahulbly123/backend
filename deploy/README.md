# Deploy & Performance Notes

This folder contains sample configuration and recommended commands for deploying the app with performance and caching in mind.

## Nginx

- Use `deploy/nginx.conf` as a starting point. It enables `gzip` and `brotli` (if compiled), sets long `Cache-Control` headers for static assets, and proxies `/api/` to the Laravel backend.
- Place frontend built output in `/var/www/frontend` and backend (Laravel) served on `127.0.0.1:8000` (php-fpm or artisan serve via supervisor).

## Laravel optimization (run on deploy)

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
php artisan queue:restart
```

Notes:
- Run `php artisan route:clear` and `config:clear` before local development when making changes.

## Static asset caching

- Serve `/static`, `/_next`, and image assets with `Cache-Control: public, max-age=2592000, immutable` where appropriate.
- Use a CDN (Cloudflare, Fastly) in front of the site and enable Brotli/gzip compression there as well.
- Configure Cloudflare with a page rule or edge cache rule for HTML pages and purge on article publish/update events.
- Set `CDN_PROVIDER=cloudflare`, `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN`, and image optimizer settings like `IMAGE_OPTIMIZER`/`CLOUDFLARE_IMAGE_PROXY_DOMAIN` or `IMAGEKIT_URL_ENDPOINT` in backend environment configuration.

## Brotli

- Install `ngx_brotli` module for nginx or let CDN handle brotli compression.

## TLS / Security

- Use Let's Encrypt or managed TLS at CDN. Add `add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;` after TLS termination.

## Further tuning

- Enable HTTP/2 and HTTP/3 on the CDN or edge where supported.
- Use image formats `webp`/`avif` for mobile devices.
- Use edge caching rules to cache HTML for non-authenticated pages where safe and invalidate on publish events.
