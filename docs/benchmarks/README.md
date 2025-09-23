# Performance Benchmark Playbook

The v1.0 release publishes reproducible HTTP throughput and latency numbers for
Bamboo. This playbook describes the tooling, environment expectations, and data
management workflow used to generate the official charts.

## Benchmark harness

1. **Prerequisites**
   - PHP 8.4 with the cURL extension.
   - A running Bamboo HTTP server (`php bin/bamboo http.serve`) listening on the
     host/port defined in `etc/server.php`.
   - Optional: a dedicated load generator host to avoid resource contention.

2. **Warm the application**
   - Hit `/` and `/metrics` once to prime opcode caches and establish Redis
     connections if applicable.

3. **Run the harness**

```
php bin/bench/http \
  --target=http://127.0.0.1:9501/ \
  --duration=60 \
  --concurrency=64 \
  --label="baseline-1.0" \
  --csv=docs/benchmarks/data/$(date +%Y%m%d)-baseline.csv
```

- `--duration` controls the sample window (seconds). Use at least 30 seconds for
  steady-state results.
- `--concurrency` maintains the specified number of in-flight requests.
- `--label` appears in the CSV output and downstream charts.
- Multiple `--header` flags may be supplied to set custom headers. Use `--body`
  when benchmarking POST/PUT requests.

Each run prints throughput, latency percentiles (p50/p95/p99), and the status
code histogram. When `--csv` is provided, the script appends a row with the
results and metadata so charts can be regenerated later.

4. **Capture metadata**
   - Record CPU model, RAM, operating system, PHP version, and OpenSwoole build.
   - Store metadata alongside the CSV file (e.g. `20240520-baseline.md`).

## Data management

- Store raw CSV files in `docs/benchmarks/data/` using the naming convention
  `YYYYMMDD-scenario.csv`.
- Commit the associated metadata notes for context (`hardware`, `php`, `git
  commit`).
- Never overwrite historical CSV files; new runs should append rows to existing
  files or create new timestamped files.

### Available datasets

- `docs/benchmarks/data/20240528-baseline.csv` &mdash; Initial cold-cache
  baseline captured for the v1.0.0 release, with metadata in
  `docs/benchmarks/data/20240528-baseline.md`.

### CSV schema

The harness writes the following columns:

| Column | Description |
|--------|-------------|
| `scenario` | Free-form label for charts (comes from `--label` or defaults to `METHOD URL`). |
| `target` | URL exercised during the run. |
| `method` | HTTP method used. |
| `concurrency` | Number of concurrent requests. |
| `duration_seconds` | Actual measured duration (seconds). |
| `requests` | Total requests completed. |
| `requests_per_second` | Throughput (requests/second). |
| `p50_ms` / `p95_ms` / `p99_ms` | Latency percentiles (milliseconds). |
| `error_count` | Failed requests. |
| `error_rate` | `error_count / requests`.

## Chart generation

`docs/tools/plot-bench.py` turns CSV files into publication-ready charts. The
script requires Python 3.9+ and `matplotlib`.

```
pip install matplotlib
python3 docs/tools/plot-bench.py docs/benchmarks/data --output docs/benchmarks
```

The script scans every CSV in the data directory and emits:

- `docs/benchmarks/throughput.png` – requests per second by concurrency level.
- `docs/benchmarks/latency.png` – p50 and p99 latency trends.

Use `--formats=png,pdf` to generate additional output formats, or `--datasets`
to restrict the render to specific files.

## Reporting checklist

- Document hardware, OS, PHP/OpenSwoole versions, and git commit hash.
- Publish throughput and latency charts alongside the raw CSV files.
- Highlight regressions greater than ±5% relative to the previous release and
  document mitigation plans.
- Verify `/metrics` exposes `bamboo_http_request_duration_seconds` and other core
  counters after each benchmark run to ensure observability remains intact.

With these artefacts in place, the v1.0 announcement can include reproducible
numbers and supporting data for the community to scrutinise.
