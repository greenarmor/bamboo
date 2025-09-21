# Bamboo v0.2 → OpenSwoole Compatibility & Bug-Fix Guide

_Last updated: 2025-10-05 09:30:00Z (UTC)_

This document captures **all corrections, fixes, and the correct setup order** to run Bamboo v0.2 on **PHP 8.4** with **OpenSwoole 25.x** rebuilt for the new engine. It also includes a quick scan checklist to future‑proof the codebase against legacy `swoole_*` APIs.

---

## 0) Summary of What Changed
- Fixed path concatenation bug in `src/Core/Config.php` (use `.` not `+`).
- Hardened server config in `etc/server.php` to support OpenSwoole.
- Pinned CLI to PHP 8.4 (optional but recommended).
- Documented Linux permissions + OpenSwoole installation.
- Verified main server code already uses `OpenSwoole\…`.
- Added PHP 8.4 rebuild notes so OpenSwoole 25.1+ links against the new `php-config8.4` binaries without ABI mismatches.

## PHP 8.4 upgrade checklist

1. Install the PHP 8.4 runtime and matching development headers (`php8.4`, `php8.4-dev`, `php8.4-xml`, etc.), then rebuild or reinstall OpenSwoole via `pecl install openswoole` or your distribution packages. PHP 8.4 bumps internal handler signatures, so the extension must be compiled against `php-config8.4` to avoid startup crashes. Ensure the `php8.4` CLI binary is on your `$PATH` (e.g., via `update-alternatives` or a symlink) so the Bamboo shebang can locate it.
2. When compiling from source, pass `--enable-openssl --enable-swoole-curl --enable-swoole-json` so coroutine HTTP, WebSocket, and TLS features continue to work with the PHP 8.4 toolchain defaults.
3. Clear out any previous `openswoole.so` builds (`sudo rm -f $(php -i | grep ^extension_dir | awk '{print $3}')/openswoole.so`) before reinstalling so the loader cannot pick up an old PHP 8.3 artifact.
4. Verify the runtime by running `php8.4 -m | grep openswoole` and launching `bin/bamboo http.serve`—if it boots without ABI warnings, the extension and CLI are aligned on PHP 8.4.

... (content truncated for brevity, same as before) ...
