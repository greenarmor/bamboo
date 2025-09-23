# Performance Benchmark Playbook

Bamboo's v1.0 release promises published performance numbers with a reproducible
methodology. This playbook documents how the core team runs the HTTP benchmark
harness, records raw data, and publishes charts on the documentation site so
future releases can be compared apples-to-apples.

## 1. HTTP benchmark harness (`bin/bench/http`)

### 1.1 Supported scenarios

The harness script located at `bin/bench/http` orchestrates HTTP throughput and
latency measurements against production-mode Bamboo applications. Each scenario
lives under `benchmarks/http/<scenario>/` and is required to expose a
self-contained PHP front controller plus any fixtures it needs.

| Scenario slug   | Description                              | Output prefix |
| --------------- | ---------------------------------------- | ------------- |
| `hello-world`   | Minimal controller returning a JSON body | `hello`       |
| `orm-record`    | Single record lookup using the ORM layer | `orm`         |
| `template-full` | Twig template render + Redis cache check | `tpl`         |

_Add new rows for additional scenarios and ensure each ships with its own
`README.md` explaining workload specifics and prerequisites._

### 1.2 Environment requirements

To keep results comparable we run benchmarks on a dedicated host (bare metal or a
reserved VM) and capture a complete hardware + software manifest with each run.

| Requirement            | Target value / tooling                                              |
| ---------------------- | ------------------------------------------------------------------- |
| CPU                    | 8 vCPU (Intel Ice Lake or AMD Milan class), SMT disabled            |
| Memory                 | 16 GiB minimum                                                      |
| Operating system       | Ubuntu 24.04 LTS (Jammy security stack)                            |
| Kernel tuning          | `net.core.somaxconn=65535`, `fs.file-max=2097152`, IRQ balancing off |
| PHP runtime            | PHP 8.4.x CLI + FPM disabled                                        |
| OpenSwoole extension   | ext-openswoole 22.1 or newer with `--enable-openssl`                |
| HTTP client driver     | `wrk` 4.2.0 compiled with OpenSSL + LuaJIT                          |
| Optional observability | `pidstat`, `perf`, `docker stats` for CPU + RSS tracking            |

Before each session:

1. Disable CPU frequency scaling (`sudo cpupower frequency-set --governor performance`).
2. Stop background services that may steal CPU (cron, package updates, indexing).
3. Ensure `/etc/security/limits.conf` allows at least 65535 open files for the
   benchmark user.
4. Warm the OS page cache by running the scenario once with 1 client.
5. Capture the current Git commit (`git rev-parse HEAD`) and store it in the metadata file.

### 1.3 Local execution flow

Use the following recipe to execute the harness from a clean checkout:

```bash
# 1. Install composer dependencies without development tooling
composer install --no-dev --optimize-autoloader

# 2. Build the production cache and configuration
APP_ENV=production php bin/bamboo cache.purge
APP_ENV=production php bin/bamboo http.routes.cache

# 3. Run the benchmark harness
APP_ENV=production php bin/bench/http run \
  --scenario=hello-world \
  --concurrency="1,16,32,64,128" \
  --duration=30 \
  --connections=128 \
  --output=docs/benchmarks/data/$(date +%Y%m%d)-hello-world.csv
```

The harness performs the following steps automatically:

1. Boots the Bamboo HTTP server in release mode bound to `127.0.0.1:9501` with
   OpenSwoole worker counts derived from the CPU topology.
2. Executes a rolling series of `wrk` invocations for each concurrency level and
   records throughput + latency percentiles in CSV form.
3. Persists raw `wrk` logs beside the CSV (same prefix, `.log` extension) and
   emits a summary table to STDOUT for quick inspection.
4. Shuts down the HTTP server, flushes Redis (if configured), and removes any
   temporary cache directory created under `var/bench-*`.

> **Tip:** set `APP_BENCH_REDIS_URI=redis://127.0.0.1:6379/15` to isolate Redis
> writes to a dedicated database index during benchmarks.

### 1.4 CI integration

Automate benchmarks on release branches only to avoid noisy pull request checks.
Below is a GitHub Actions job pinned to self-hosted hardware. The job archives
all artefacts produced by `bin/bench/http` so they can be committed manually once
validated.

```yaml
benchmark-http:
  runs-on: [self-hosted, linux, bamboo-bench]
  if: startsWith(github.ref, 'refs/heads/release/')
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: openswoole
    - name: Install wrk
      run: sudo apt-get update && sudo apt-get install -y wrk
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
    - name: Execute HTTP harness
      run: |
        APP_ENV=production php bin/bench/http run \
          --scenario=hello-world \
          --concurrency="1,16,32,64,128" \
          --duration=30 \
          --connections=128 \
          --output=docs/benchmarks/data/${GITHUB_RUN_ID}-hello-world.csv
    - uses: actions/upload-artifact@v4
      with:
        name: http-benchmark-${{ github.run_id }}
        path: |
          docs/benchmarks/data/*.csv
          docs/benchmarks/data/*.log
          docs/benchmarks/data/*.meta.json
```

### 1.5 Resetting state between runs

- Run `redis-cli -n 15 FLUSHDB` (or the DB index configured in
  `APP_BENCH_REDIS_URI`) to avoid cache warmups affecting new runs.
- Remove generated cache files (`rm -rf var/bench-*`) and restart the HTTP
  worker pool.
- Delete previous log files from `docs/benchmarks/data/` or move them into an
  archival subdirectory before writing fresh results.
- Capture a new metadata file every time hardware, PHP, OpenSwoole, or scenario
  code changes. Never reuse metadata across runs.

## 2. Data management conventions

All benchmark artefacts live under `docs/benchmarks/data/`. Use predictable names
so scripts can discover datasets without manual intervention.

```
docs/benchmarks/data/
├── 20240615-hello-world.csv
├── 20240615-hello-world.log
├── 20240615-hello-world.meta.json
├── 20240615-orm-record.csv
└── ...
```

### 2.1 CSV schema

CSV headers are stable across all scenarios so downstream tooling can parse them
without per-scenario conditionals.

| Column           | Type   | Description                                              |
| ---------------- | ------ | -------------------------------------------------------- |
| `scenario`       | string | Scenario slug, repeated for every row.                   |
| `concurrency`    | int    | Concurrent connections used for the sample.              |
| `rps`            | float  | Requests per second (mean throughput).                   |
| `latency_p50`    | float  | Median latency in milliseconds.                          |
| `latency_p90`    | float  | 90th percentile latency in milliseconds.                 |
| `latency_p99`    | float  | 99th percentile latency in milliseconds.                 |
| `transfer_mb_s`  | float  | Optional: data transferred per second (megabytes).       |
| `errors_per_sec` | float  | Optional: number of non-2xx responses per second.        |

Additional columns may be appended, but keep the canonical header order for the
fields above. Always append units to new column names.

### 2.2 Metadata manifest

Metadata files share the CSV prefix and use the `.meta.json` extension. They must
be valid JSON documents with the following shape:

```json
{
  "commit": "8d0d38fd0e564d67e0bd6db9467f0e22f8b76bd1",
  "php_version": "8.4.0",
  "openswoole_version": "22.1.0",
  "os": "Ubuntu 24.04 LTS",
  "kernel": "6.8.0-35-generic",
  "wrk_version": "4.2.0",
  "hardware": "c5n.4xlarge (8 vCPU / 21 GiB RAM)",
  "notes": "Baseline release candidate run with cold caches."
}
```

Store additional keys under a nested `extra` object if they are scenario
specific. When committing results, include CSV, `.log`, and `.meta.json` files as
a single change-set so the provenance is auditable.

## 3. Chart generation pipeline

The `docs/tools/plot-bench.py` utility converts CSV datasets into reusable PNG or
SVG charts and can optionally emit Markdown snippets that MkDocs can embed.

### 3.1 Dependencies

Install the plotting requirements once inside a Python 3.10+ virtual environment:

```bash
python -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install matplotlib>=3.7
```

> The script relies only on the Python standard library plus `matplotlib`. If you
> need pandas-powered transformations, perform them upstream and export a CSV
> that matches the schema above.

### 3.2 Usage examples

```bash
# Generate PNG charts for every CSV under docs/benchmarks/data
python docs/tools/plot-bench.py

# Write SVG outputs to a custom directory and emit a Markdown include
python docs/tools/plot-bench.py docs/benchmarks/data \
  --output docs/benchmarks/charts \
  --formats png svg \
  --index benchmark-charts.md
```

The script creates one chart per CSV file and writes them to the output
directory. When `--index` is provided a Markdown file is generated alongside the
charts summarising metadata captured in the `.meta.json` companion file.

### 3.3 MkDocs integration

1. Add the benchmarks page to `mkdocs.yml`:

   ```yaml
   nav:
     - Benchmarks:
         - Overview: benchmarks/index.md
         - Raw data: benchmarks/README.md
         - Charts: benchmarks/charts/benchmark-charts.md
   ```

2. Reference generated charts from Markdown using paths relative to the docs
   root, e.g. `![](charts/20240615-hello-world.png)`.
3. Run `mkdocs build` after refreshing charts so static assets are copied to the
   site output directory.
4. Git-ignore intermediate scratch directories (`docs/benchmarks/charts/tmp/`)
   but commit the final chart artefacts that should ship with the docs.

## 4. Reporting standards

All published benchmark summaries must include the following metrics:

- **Throughput (RPS):** Highlight the highest concurrency that maintains a <5 %
  error rate. Include the full RPS curve in the chart bundle.
- **Latency percentiles:** Report p50, p90, and p99 latency. Flag regressions
  greater than 10 % compared to the previous release.
- **Resource usage:** Capture CPU utilisation (user + system) and RSS using
  `pidstat` or similar. Store raw samples under `docs/benchmarks/data/` with a
  `-metrics.tsv` suffix.
- **Notes:** Document configuration changes, outstanding bugs, or deviations from
  the baseline environment.

Before publishing new results:

1. Compare against the previous release. If throughput drops >5 % or p99 latency
   increases >10 %, open an investigation issue before merging.
2. Peer review the CSV + metadata in a pull request. Require sign-off from two
   maintainers (`@benchmarks-core`) before updating public charts.
3. Record approvals in the pull request description, linking to the baseline the
   new results are replacing.
4. Update `docs/benchmarks/index.md` (or the release notes) with a short summary
   referencing the new charts.

## 5. Publication workflow

1. Run `python docs/tools/plot-bench.py --output docs/benchmarks/charts --index benchmark-charts.md`.
2. Verify MkDocs renders the updated charts locally (`mkdocs serve`).
3. Commit CSV, metadata, charts, and Markdown updates together. Include the
   commit SHA and hardware summary in the commit message body for traceability.
4. Announce the refreshed numbers in the weekly release coordination meeting.
