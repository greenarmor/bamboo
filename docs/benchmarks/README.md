# Performance Benchmark Playbook (Stub)

> **Status:** Scaffold. Fill in procedures, tooling requirements, and sample outputs before declaring v1.0 readiness.

Bamboo's v1.0 release promises published performance numbers with reproducible methodology. Use this playbook to coordinate benchmark capture and publication.

## Benchmark harness

- [ ] Document how to run `bin/bench/http` locally and in CI.
- [ ] Capture environment prerequisites (hardware profile, OpenSwoole tuning, PHP configuration).
- [ ] Explain how to reset state between runs and collect artefacts.

## Data management

- [ ] Store raw result files under `docs/benchmarks/data/` using predictable naming (`YYYYMMDD-scenario.csv`).
- [ ] Record metadata (commit SHA, PHP version, operating system) alongside each dataset.
- [ ] Describe where to publish summary tables and charts in the documentation site.

## Chart generation

- [ ] Implement the `docs/tools/plot-bench.py` script to turn raw data into charts.
- [ ] Provide usage examples and required Python dependencies.
- [ ] Outline the CI/CD integration that regenerates charts on demand.

## Reporting checklist

- [ ] Define required metrics (requests/second, latency percentiles, resource usage).
- [ ] Include guidance for interpreting results and regression thresholds.
- [ ] Track approvals needed before publishing benchmark updates.

