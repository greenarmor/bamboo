# Task Stub: Publish the inaugural benchmark dataset

## Summary

Capture and commit the first cold-cache HTTP benchmark run required for the v1.0
release announcement. The goal is to execute the maintained harness, document
hardware/runtime metadata, and store the CSV + notes under `docs/benchmarks/`
so charts can be generated for release materials.

## Background

- [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md) calls for scheduling the
  initial benchmark run and landing the resulting dataset under
  `docs/benchmarks/data/`.
- The benchmarking harness and workflow are documented in
  [`docs/benchmarks/README.md`](../benchmarks/README.md), but the `data/`
  directory is currently empty.
- Publishing the dataset unblocks chart generation via
  `docs/tools/plot-bench.py` and lets the announcement cite reproducible numbers.

## Definition of done

- [ ] At least one CSV capturing the baseline run (with label, concurrency,
      throughput, latency percentiles) lives in `docs/benchmarks/data/`.
- [ ] A companion Markdown or YAML metadata file records hardware specs, OS,
      PHP/OpenSwoole versions, git commit hash, and any notable tuning.
- [ ] `docs/benchmarks/README.md` references the new dataset and, if necessary,
      notes any deviations from the default workflow.
- [ ] Optional: throughput/latency charts regenerated to confirm the dataset is
      compatible with the plotting tool.

## Suggested implementation

1. Provision or schedule dedicated hardware that mirrors the target production
   baseline. Document CPU, RAM, kernel, and network configuration.
2. Deploy Bamboo at the latest commit intended for the v1.0 release and warm the
   application per the playbook instructions.
3. Execute `php bin/bench/http` with the agreed concurrency/duration settings and
   capture the CSV output inside `docs/benchmarks/data/` (e.g.
   `20240520-baseline.csv`).
4. Write a metadata note (e.g. `20240520-baseline.md`) capturing environment
   details and relevant command invocations.
5. Optionally run `python3 docs/tools/plot-bench.py docs/benchmarks/data` to
   regenerate charts and confirm there are no parser regressions.
6. Commit the new artefacts and update the roadmap checklist once merged.

## References

- Benchmark workflow: [`docs/benchmarks/README.md`](../benchmarks/README.md)
- Data storage location: [`docs/benchmarks/data/`](../benchmarks/data)
- Plotting tool: [`docs/tools/plot-bench.py`](../tools/plot-bench.py)
- Roadmap tracker: [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md)
