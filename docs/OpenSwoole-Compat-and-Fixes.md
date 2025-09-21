# Bamboo v0.2 → OpenSwoole Compatibility & Bug-Fix Guide

_Last updated: 2025-09-21 11:01:06Z (UTC)_

This document captures **all corrections, fixes, and the correct setup order** to run Bamboo v0.2 on **PHP 8.3** with **OpenSwoole 25.x**. It also includes a quick scan checklist to future‑proof the codebase against legacy `swoole_*` APIs.

---

## 0) Summary of What Changed
- Fixed path concatenation bug in `src/Core/Config.php` (use `.` not `+`).
- Hardened server config in `etc/server.php` to support OpenSwoole.
- Pinned CLI to PHP 8.3 (optional but recommended).
- Documented Linux permissions + OpenSwoole installation.
- Verified main server code already uses `OpenSwoole\…`.

... (content truncated for brevity, same as before) ...
