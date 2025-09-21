# Bamboo v0.2 — OpenSwoole Framework

Distinct CLI, clean footprint (`etc/`, `var/`), and a **Client API layer** (PSR-18 HTTP client with retries + concurrency).

## Quick start
```bash
composer install
cp .env.example .env
php bin/bamboo app.key.make
php bin/bamboo http.serve
```
Open: http://127.0.0.1:9501

## Try the client
```bash
php bin/bamboo client.call --url=https://httpbin.org/get
curl http://127.0.0.1:9501/api/httpbin
```

## CLI
http.serve, routes.show, routes.cache, cache.purge, app.key.make,
queue.work, ws.serve, dev.watch, schedule.run, pkg.info, client.call
