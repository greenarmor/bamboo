# Bamboo on PHP 8.4 — Installation & Configuration Guide

This guide walks through a full Bamboo v0.2 installation on **PHP 8.4** with the OpenSwoole runtime. It targets Ubuntu/Debian hosts but calls out the adjustments needed for macOS and other Linux distributions. Follow every step to ensure the CLI, HTTP server, Redis-backed queues, and optional database integration all function correctly.

---

## 1. Prerequisites

| Requirement | Notes |
| --- | --- |
| 64-bit Linux (Ubuntu 22.04+, Debian 12+) or macOS 14+ | Bamboo is tested primarily on Linux. macOS works for development but requires Homebrew-installed services. |
| PHP 8.4 CLI & FPM | Bamboo requires the PHP 8.4 runtime and development headers so that OpenSwoole can be compiled against it. |
| Composer 2.5+ | Needed to install PHP dependencies. |
| Git 2.30+ | Used to clone the repository. |
| Redis 6+ | Provides the queue backend (required for queue.work demos). |
| MySQL 8+, MariaDB 10.6+, or SQLite | Optional but required if you enable the database layer. |
| Build toolchain | `build-essential`, `pkg-config`, `libssl-dev`, and `zlib1g-dev` are required for compiling OpenSwoole. |

> **Tip:** Run everything from a shell session with sudo privileges. If you are on macOS, replace `apt` commands with their Homebrew equivalents (`brew install ...`).

---

## 2. Install PHP 8.4 and extensions

### 2.1 Ubuntu/Debian

```bash
# Enable the maintained PHP packaging repository (if you do not already use it)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.4 and required extensions
sudo apt install -y \
  php8.4 php8.4-cli php8.4-fpm php8.4-common php8.4-dev \
  php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-intl \
  php8.4-sqlite3 php8.4-mysql php8.4-pgsql php8.4-redis php8.4-gd

# Ensure the PHP 8.4 CLI is the default on your PATH
sudo update-alternatives --set php /usr/bin/php8.4
```

The `php8.4-dev` package provides `php-config8.4`, which OpenSwoole requires. The other extensions align with the features that Bamboo and its dependencies expect (`curl`, `mbstring`, `pdo_*`, `intl`, `redis`, etc.).

### 2.2 macOS (Homebrew)

```bash
brew update
brew install php@8.4 composer redis mysql
brew services start redis
brew services start mysql
```

Add the PHP binary to your path if Homebrew does not do this automatically:

```bash
echo 'export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

---

## 3. Install build dependencies and OpenSwoole

OpenSwoole is the coroutine HTTP server that powers Bamboo. It must be compiled against PHP 8.4.

```bash
# Toolchain & headers (Ubuntu/Debian)
sudo apt install -y build-essential autoconf pkg-config libssl-dev zlib1g-dev libcurl4-openssl-dev

# Optional: remove old OpenSwoole builds if you previously installed for PHP 8.3
sudo rm -f "$(php -i | grep '^extension_dir' | awk '{print $3}')/openswoole.so"

# Install through PECL (works on Linux and macOS)
printf "\n" | sudo pecl install openswoole
```

When the PECL installer prompts for enabling additional features, accept the defaults or enable the ones you need:

- `--enable-openssl` (recommended for HTTPS).
- `--enable-swoole-curl` (keeps coroutine HTTP functionality).
- `--enable-swoole-json` (required for JSON helpers).

Finally, register the extension:

```bash
echo "extension=openswoole" | sudo tee /etc/php/8.4/mods-available/openswoole.ini
sudo phpenmod openswoole
```

Verify the setup:

```bash
php -v
php -m | grep openswoole
php --ri openswoole
```

All commands should report PHP 8.4 and a loaded OpenSwoole module.

---

## 4. Install Composer and project dependencies

If Composer is not already available:

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

Composer will pull in all runtime dependencies declared in [`composer.json`](https://github.com/greenarmor/bamboo/blob/main/composer.json) such as FastRoute, Nyholm PSR-7, Guzzle, Predis, Illuminate Database, and Monolog.

---

## 5. Project configuration

1. Copy the environment template and generate the application key:
   ```bash
   cp .env.example .env
   php bin/bamboo app.key.make
   ```
   The key is stored in `.env` and used for encryption helpers.

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

4. Understand the configuration files in [`etc/`](https://github.com/greenarmor/bamboo/tree/main/etc):
   - `etc/app.php` reads core app settings (`APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `LOG_FILE`).
   - `etc/server.php` maps `.env` values to OpenSwoole server options (workers, task workers, static files).
   - `etc/cache.php` controls where cache artifacts are saved.
   - `etc/http.php` defines default timeouts, headers, and retry policies for the bundled PSR-18 HTTP client along with service overrides.
   - `etc/redis.php` and `etc/ws.php` configure the Redis queue connection and WebSocket server endpoint.
   - `etc/database.php` defines database connections for the optional Eloquent ORM integration.

Update these files if you need to commit environment-specific defaults to source control; otherwise rely on `.env` overrides for per-host customization.

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
  Adjust `.env` with your credentials. For SQLite, point `DB_DATABASE` to an absolute file path and ensure the containing directory is writable.

- **Supervisor/systemd (optional)**: To run Bamboo as a daemon, create a systemd unit invoking `php /path/to/bamboo/bin/bamboo http.serve`. Ensure the service account has permissions to the project directory.

---

## 7. Running Bamboo

Start the OpenSwoole HTTP server:

```bash
php bin/bamboo http.serve
```

Open `http://127.0.0.1:9501` in a browser to confirm the welcome JSON. For other core commands:

```bash
php bin/bamboo routes.show   # Inspect registered routes
php bin/bamboo queue.work    # Start the Redis-backed queue worker
php bin/bamboo ws.serve      # Start the WebSocket echo server
php bin/bamboo client.call --url=https://httpbin.org/get
```

If you want hot reloading during development, keep `dev.watch` running in another terminal. It watches source changes and restarts the server automatically.

---

## 8. Troubleshooting checklist

| Symptom | Resolution |
| --- | --- |
| `PHP Warning:  Module "openswoole" is already loaded` | Remove duplicate `extension=openswoole` lines from `php.ini` or `/etc/php/8.4/mods-available/openswoole.ini`. |
| `undefined symbol: php_json_encode` on startup | Rebuild OpenSwoole with `--enable-swoole-json` against PHP 8.4. |
| `Class "Redis" not found` or inability to enqueue jobs | Ensure `php8.4-redis` or the `redis` PECL extension is installed/enabled; check `php -m`. |
| HTTP server exits immediately | Verify that port 9501 is free and that the user has permission to bind to it. Check logs in `var/log/app.log`. |
| `APP_KEY` empty warning | Run `php bin/bamboo app.key.make` again to populate the key. |
| OpenSwoole fails to build (`fatal error: openssl/...`) | Ensure `libssl-dev` and `pkg-config` are installed before running `pecl install openswoole`. |

For more background on PHP 8.4 compatibility fixes, consult [`docs/OpenSwoole-Compat-and-Fixes.md`](./OpenSwoole-Compat-and-Fixes.md).

---

## 9. Next steps

- Commit your `.env` overrides to a `.env.local` (or similar) file outside version control.
- Configure CI/CD runners with the same PHP 8.4 + OpenSwoole toolchain so deployments match local development.
- Explore the roadmap and CLI capabilities in the [README](https://github.com/greenarmor/bamboo/blob/main/README.md).

Bamboo should now be fully operational on PHP 8.4.
