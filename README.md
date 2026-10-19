<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Development

## Deployment and troubleshooting (egg sensor bridge)

The serial bridge sends readings to the local Laravel API. Use one configured base URL (`LAYRATE_API_BASE_URL`) for both sensor ingestion and relay polling. The safe Pi default is `http://127.0.0.1`; do not use `localhost` because it may resolve to `::1`. The current Pi Nginx loopback API vhost is IPv4-only. If adding IPv6 support, add and test a dedicated `[::1]` loopback API vhost before changing the bridge URL. The captive-portal catch-all must not be used as an API path.

The bridge refuses redirects and non-JSON/unexpected API acknowledgements, retries with backoff, and persists pending batches in `/var/lib/layrate-serial/queue.jsonl`. Successful posts are HTTP 200 JSON acknowledgements; failures include the URL, status, sequence ID, and response snippet in `journalctl`. A periodic heartbeat reports `sent`, `failed`, `queued`, `serial_connected`, serial activity time, and last successful delivery. A local read-only health endpoint is available at `http://127.0.0.1:8765/health`.

Run the bridge unit tests from `serial-bridge/`:

```bash
python3 -m unittest test_bridge test_delivery -v
```

The database/API smoke test sends a marked health probe that does not change production readings or egg counts. Set `LAYRATE_HEALTH_PROBE_SERIAL`, `LAYrate_DEVICE_KEY`, and the DB connection variables, then run:

```bash
python3 serial-bridge/smoke-test.py --base-url http://127.0.0.1
```

Optionally provide `--ipv6-url http://[::1] --expect-ipv6 refused` if IPv6 loopback is intentionally not served, or set `--expect-ipv6 success` after configuring a dedicated IPv6 API listener. The test verifies a genuine app JSON acknowledgement and verifies the active hardware item still exists while its health timestamp remains unchanged. The systemd health timer runs `serial-bridge/health-check.sh` every minute and records failures in its unit journal. It requires `LAYRATE_HEALTH_PROBE_SERIAL` to identify an active sensor and posts a `health_probe` marker acknowledged without changing sensor readings, health timestamps, production logs, or counts.

Incident checklist when readings stop:

```bash
curl -v http://localhost/api/relay/command
curl -v http://127.0.0.1/api/relay/command
ss -ltnp
journalctl -u layrate-serial -n 100 --no-pager
curl -v http://127.0.0.1:8765/health
systemctl status layrate-serial-health.timer
```

If the `localhost` request shows a `302` to the portal while `127.0.0.1` reaches Laravel, check IPv6/name resolution and the Nginx loopback listeners. Distinguish `serial_activity_at` (Arduino output reached the bridge) from `delivery_at` and queued count (bridge-to-app delivery). Never use an egg-bearing production serial number for a smoke test.

After editing Blade views or CSS, rebuild Tailwind and clear view cache before verifying visually:

```bash
npm run build
php artisan view:clear
```

Always hard-reload the browser (Ctrl+F5 / Cmd+Shift+R) — stale browser or compiled-view cache can cause correct source to appear broken.

### Local dev server (GD / chart-in-PDF support)

PDF chart-image embedding requires the GD extension. On this Arch Linux dev environment the `php-gd` package installs `gd.so` to `/usr/lib/php/modules/` (the correct `extension_dir`), but no corresponding `gd.ini` is present in `/etc/php/conf.d/`, so PHP never loads it.

Until this is resolved at the system level, use the provided launcher script instead of `php artisan serve` directly:

```bash
./scripts/serve-local.sh --host=0.0.0.0 --port=8000
```

The script sets `PHP_INI_SCAN_DIR` to include `.dev/php-ext/gd.ini` and `LD_LIBRARY_PATH` to include the bundled shared libraries (`libgd.so.3`, `libXpm.so.4`). These live in `.dev/php-ext/` (git-ignored).

### Worker count

The built-in PHP dev server runs a single worker by default. Since this app uses Server-Sent Events (SSE) endpoints that hold a connection for up to 10 seconds, the single worker can become exhausted if multiple SSE connections are in-flight (e.g. tab-switching in Egg Management). Set `PHP_CLI_SERVER_WORKERS=4` before starting the server to avoid blocking:

```bash
PHP_CLI_SERVER_WORKERS=4 ./scripts/serve-local.sh --host=0.0.0.0 --port=8000
```

Requires PHP 8.5+ (the app server is already on 8.5.8).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
