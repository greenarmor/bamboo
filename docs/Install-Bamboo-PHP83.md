# Bamboo on PHP 8.3 — Installation & Configuration Guide

This guide explains how to install and configure Bamboo v0.2 on **PHP 8.3** with the OpenSwoole runtime. It targets Ubuntu/Debian systems but also documents the macOS/Homebrew equivalents. Follow the steps to bring up the CLI, OpenSwoole HTTP server, Redis queue worker, and optional database layer.

---

## 1. Prerequisites

| Requirement | Notes |
| --- | --- |
| 64-bit Linux (Ubuntu 22.04+, Debian 12+) or macOS 13+ | Bamboo is primarily validated on Linux. macOS works for development but needs Homebrew services. |
| PHP 8.3 CLI & FPM | Install the PHP 8.3 runtime plus development headers so OpenSwoole can compile. |
| Composer 2.5+ | Required to install PHP dependencies. |
| Git 2.30+ | Used to clone the repository. |
| Redis 6+ | Supplies the queue backend for `queue.work`. |
| MySQL 8+, MariaDB 10.6+, or SQLite | Optional unless you enable the database layer. |
| Build toolchain | `build-essential`, `pkg-config`, `libssl-dev`, and `zlib1g-dev` are necessary to build OpenSwoole. |

> **Tip:** Work from a shell session with sudo privileges. On macOS switch `apt` commands to Homebrew equivalents (`brew install ...`).

---

## 2. Install PHP 8.3 and extensions

### 2.1 Ubuntu/Debian

```bash
# Enable the maintained PHP packaging repository (if you do not already use it)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and required extensions
sudo apt install -y \
  php8.3 php8.3-cli php8.3-fpm php8.3-common php8.3-dev \
  php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl \
  php8.3-sqlite3 php8.3-mysql php8.3-pgsql php8.3-redis php8.3-gd

# Ensure the PHP 8.3 CLI is the default on your PATH
sudo update-alternatives --set php /usr/bin/php8.3
```

The `php8.3-dev` package ships `php-config8.3`, which OpenSwoole needs for compilation. The other extensions mirror what Bamboo and its dependencies expect (`curl`, `mbstring`, `pdo_*`, `intl`, `redis`, etc.).

### 2.2 macOS (Homebrew)

```bash
brew update
brew install php@8.3 composer redis mysql
brew services start redis
brew services start mysql
```

Add the PHP binary to your path if Homebrew does not do this automatically:

```bash
echo 'export PATH="/opt/homebrew/opt/php@8.3/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

---

## 3. Install build dependencies and OpenSwoole

OpenSwoole is the coroutine HTTP server that powers Bamboo. It must be compiled against PHP 8.3.

```bash
# Toolchain & headers (Ubuntu/Debian)
sudo apt install -y build-essential autoconf pkg-config libssl-dev zlib1g-dev libcurl4-openssl-dev

# Optional: remove old OpenSwoole builds if you previously installed for PHP 8.2
sudo rm -f "$(php -i | grep '^extension_dir' | awk '{print $3}')/openswoole.so"

# Install through PECL (works on Linux and macOS)
printf "\n" | sudo pecl install openswoole
```

When prompted, accept the defaults or enable features you require:

- `--enable-openssl` (recommended for HTTPS).
- `--enable-swoole-curl` (keeps coroutine HTTP functionality).
- `--enable-swoole-json` (required for JSON helpers).

Register the extension:

```bash
echo "extension=openswoole" | sudo tee /etc/php/8.3/mods-available/openswoole.ini
sudo phpenmod openswoole
```

Verify the setup:

```bash
php -v
php -m | grep openswoole
php --ri openswoole
```

All commands should report PHP 8.3 and a loaded OpenSwoole module.

---

## 4. Install Composer and project dependencies

If Composer is not already installed:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

Clone the Bamboo repository and install PHP packages:

```bash
cd /opt # or any working directory you prefer
sudo git clone https://github.com/greenarmor/bamboo.git
cd bamboo
composer install
```

Composer installs the runtime dependencies defined in [`composer.json`](../composer.json) such as FastRoute, Nyholm PSR-7, Guzzle, Predis, Illuminate Database, and Monolog.

---

## 5. Project configuration

1. Copy the environment template and generate the application key:
   ```bash
   cp .env.example .env
   php bin/bamboo app.key.make
   ```
   The key is stored in `.env` and powers encryption helpers.

2. Review the `.env` file:
   - **HTTP server**: `HTTP_HOST`, `HTTP_PORT`, `HTTP_WORKERS`, `TASK_WORKERS`, `MAX_REQUESTS`, `STATIC_ENABLED`.
   - **Redis**: `REDIS_URL` (e.g., `tcp://127.0.0.1:6379`) and `REDIS_QUEUE`.
   - **Database**: Set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` if you use Eloquent.
   - **WebSocket**: `WS_HOST` and `WS_PORT`.
   - **Logging**: `LOG_FILE` defaults to `var/log/app.log`; change it if your runtime uses a different writable path.

3. Prepare runtime directories and permissions:
   ```bash
   mkdir -p var/cache var/log
   sudo chown -R $USER:$USER var
   sudo chmod -R 775 var
   ```
   The cache directory stores route caches and other runtime artifacts, while the log directory receives application logs.

4. Understand the configuration files in [`etc/`](../etc):
   - `etc/app.php` reads core app settings (`APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `LOG_FILE`).
   - `etc/server.php` maps `.env` values to OpenSwoole server options (workers, task workers, static files).
   - `etc/cache.php` controls where cache artifacts are stored.
   - `etc/http.php` defines default timeouts, headers, and retry policies for the bundled PSR-18 HTTP client along with service overrides.
   - `etc/redis.php` and `etc/ws.php` configure the Redis queue connection and WebSocket server endpoint.
   - `etc/database.php` defines database connections for the optional Eloquent ORM integration.

Update these files if you need to commit environment-specific defaults; otherwise rely on `.env` overrides for per-host customization.

---

## 6. Supporting services

- **Redis**: Install and start Redis so the queue worker can connect.
  ```bash
  sudo apt install -y redis-server
  sudo systemctl enable --now redis-server
  ```

- **Database (optional)**: Install MySQL or MariaDB if you plan to use Eloquent.
  ```bash
  sudo apt install -y mysql-server
  sudo mysql_secure_installation
  ```
  Update `.env` with your credentials. For SQLite, point `DB_DATABASE` to an absolute file path and ensure the containing directory is writable.

- **Supervisor/systemd (optional)**: To run Bamboo as a daemon, create a systemd unit invoking `php /path/to/bamboo/bin/bamboo http.serve`. Ensure the service account has permissions to the project directory.

---

## 7. Running Bamboo

Start the OpenSwoole HTTP server:

```bash
php bin/bamboo http.serve
```

Open `http://127.0.0.1:9501` in a browser to confirm the welcome JSON. Other useful commands:

```bash
php bin/bamboo routes.show   # Inspect registered routes
php bin/bamboo queue.work    # Start the Redis-backed queue worker
php bin/bamboo ws.serve      # Start the WebSocket echo server
php bin/bamboo client.call --url=https://httpbin.org/get
```

For hot reloading during development, keep `dev.watch` running in another terminal. It watches source changes and restarts the server automatically.

---

## 8. Troubleshooting checklist

| Symptom | Resolution |
| --- | --- |
| `PHP Warning:  Module "openswoole" is already loaded` | Remove duplicate `extension=openswoole` lines from `php.ini` or `/etc/php/8.3/mods-available/openswoole.ini`. |
| `undefined symbol: php_json_encode` on startup | Rebuild OpenSwoole with `--enable-swoole-json` against PHP 8.3. |
| `Class "Redis" not found` or inability to enqueue jobs | Ensure `php8.3-redis` or the `redis` PECL extension is installed/enabled; check `php -m`. |
| HTTP server exits immediately | Verify that port 9501 is free and that the user has permission to bind to it. Check logs in `var/log/app.log`. |
| `APP_KEY` empty warning | Run `php bin/bamboo app.key.make` again to populate the key. |
| OpenSwoole fails to build (`fatal error: openssl/...`) | Install `libssl-dev` and `pkg-config` before running `pecl install openswoole`. |

Consult [`docs/OpenSwoole-Compat-and-Fixes.md`](./OpenSwoole-Compat-and-Fixes.md) for additional OpenSwoole notes.

---

## 9. Next steps

- Commit your `.env` overrides to a `.env.local` (or similar) file outside version control.
- Configure CI/CD runners with the same PHP 8.3 + OpenSwoole toolchain so deployments match local development.
- Explore the roadmap and CLI capabilities in the [README](../README.md).

Bamboo should now be fully operational on PHP 8.3.
