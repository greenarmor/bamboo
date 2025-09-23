#!/usr/bin/env python3
"""Generate throughput and latency charts from Bamboo benchmark CSV files."""

from __future__ import annotations

import argparse
import csv
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, List


@dataclass
class DataPoint:
    concurrency: int
    requests_per_second: float
    p50_ms: float
    p95_ms: float
    p99_ms: float


@dataclass
class Dataset:
    label: str
    source: Path
    points: List[DataPoint]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Plot throughput and latency trends for Bamboo benchmark runs",
    )
    parser.add_argument(
        "data_dir",
        type=Path,
        nargs="?",
        default=Path("docs/benchmarks/data"),
        help="Directory containing CSV benchmark results.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("docs/benchmarks"),
        help="Directory where charts will be written.",
    )
    parser.add_argument(
        "--formats",
        default="png",
        help="Comma separated list of output formats (png,pdf,svg).",
    )
    parser.add_argument(
        "--datasets",
        nargs="*",
        help="Optional list of dataset basenames to include (matches CSV stem).",
    )
    parser.add_argument(
        "--title-prefix",
        default="Bamboo v1.0 benchmarks",
        help="Prefix added to generated chart titles.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    formats = [ext.strip() for ext in args.formats.split(",") if ext.strip()]
    if not formats:
        print("No output formats specified.", file=sys.stderr)
        return 1

    datasets = load_datasets(args.data_dir, args.datasets)
    if not datasets:
        print(f"No benchmark datasets found in {args.data_dir}.")
        return 0

    try:
        import matplotlib.pyplot as plt
    except ImportError:  # pragma: no cover - dependency check
        print(
            "matplotlib is required to render charts. Install it with 'pip install matplotlib'.",
            file=sys.stderr,
        )
        return 1

    args.output.mkdir(parents=True, exist_ok=True)

    throughput_fig = plot_throughput(plt, datasets, args.title_prefix)
    latency_fig = plot_latency(plt, datasets, args.title_prefix)

    for fmt in formats:
        ext = fmt.lower()
        throughput_path = args.output / f"throughput.{ext}"
        latency_path = args.output / f"latency.{ext}"
        throughput_fig.savefig(throughput_path, dpi=180, bbox_inches="tight")
        latency_fig.savefig(latency_path, dpi=180, bbox_inches="tight")
        print(f"Wrote {throughput_path.relative_to(Path.cwd())}")
        print(f"Wrote {latency_path.relative_to(Path.cwd())}")

    plt.close(throughput_fig)
    plt.close(latency_fig)
    return 0


def load_datasets(data_dir: Path, filter_names: Iterable[str] | None) -> List[Dataset]:
    if not data_dir.exists():
        return []

    selected = {Path(name).stem for name in filter_names} if filter_names else None
    datasets: List[Dataset] = []

    for csv_path in sorted(data_dir.glob("*.csv")):
        if selected is not None and csv_path.stem not in selected:
            continue

        with csv_path.open(newline="") as handle:
            reader = csv.DictReader(handle)
            points: List[DataPoint] = []
            label: str | None = None
            for row in reader:
                try:
                    label = row.get("scenario") or label or csv_path.stem
                    concurrency = int(float(row["concurrency"]))
                    rps = float(row["requests_per_second"])
                    p50 = float(row.get("p50_ms", 0.0))
                    p95 = float(row.get("p95_ms", 0.0))
                    p99 = float(row.get("p99_ms", 0.0))
                except (KeyError, TypeError, ValueError) as exc:
                    print(f"Skipping row in {csv_path.name}: {exc}", file=sys.stderr)
                    continue
                points.append(DataPoint(concurrency, rps, p50, p95, p99))

        if not points:
            continue

        points.sort(key=lambda p: p.concurrency)
        datasets.append(Dataset(label or csv_path.stem, csv_path, points))

    return datasets


def plot_throughput(plt, datasets: List[Dataset], title_prefix: str):
    fig, ax = plt.subplots(figsize=(7.5, 4.5))
    for dataset in datasets:
        x = [point.concurrency for point in dataset.points]
        y = [point.requests_per_second for point in dataset.points]
        ax.plot(x, y, marker="o", label=dataset.label)

    ax.set_title(f"{title_prefix} – throughput")
    ax.set_xlabel("Concurrent requests")
    ax.set_ylabel("Requests / second")
    ax.grid(True, linestyle="--", linewidth=0.5, alpha=0.6)
    ax.legend(loc="best")
    return fig


def plot_latency(plt, datasets: List[Dataset], title_prefix: str):
    fig, ax = plt.subplots(figsize=(7.5, 4.5))
    for dataset in datasets:
        x = [point.concurrency for point in dataset.points]
        p50 = [point.p50_ms for point in dataset.points]
        p99 = [point.p99_ms for point in dataset.points]
        ax.plot(x, p50, marker="o", label=f"{dataset.label} p50")
        ax.plot(x, p99, marker="o", linestyle="--", label=f"{dataset.label} p99")

    ax.set_title(f"{title_prefix} – latency")
    ax.set_xlabel("Concurrent requests")
    ax.set_ylabel("Latency (ms)")
    ax.grid(True, linestyle="--", linewidth=0.5, alpha=0.6)
    ax.legend(loc="best")
    return fig


if __name__ == "__main__":
    raise SystemExit(main())
#!/usr/bin/env python3
"""Plot Bamboo benchmark results and emit MkDocs-friendly artefacts.

The utility scans one or more directories for CSV files following the
`docs/benchmarks/README.md` schema and renders throughput/latency charts using
matplotlib. Metadata stored in `<dataset>.meta.json` is surfaced in chart captions
and can optionally be exported to a Markdown include for the documentation site.

Dependencies
------------
* Python 3.10+
* matplotlib >= 3.7 (`python -m pip install matplotlib>=3.7`)

Usage examples
--------------
>>> # Generate PNG charts for every CSV under docs/benchmarks/data
>>> python docs/tools/plot-bench.py

>>> # Produce PNG + SVG outputs and a Markdown snippet for MkDocs
>>> python docs/tools/plot-bench.py docs/benchmarks/data \
...     --output docs/benchmarks/charts \
...     --formats png svg \
...     --index benchmark-charts.md
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import sys
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from textwrap import fill
from typing import Dict, Iterable, List, Sequence

import matplotlib.pyplot as plt

LATENCY_LABELS: Dict[str, str] = {
    "latency_p50": "p50",
    "latency_p90": "p90",
    "latency_p95": "p95",
    "latency_p99": "p99",
    "latency_p999": "p99.9",
}


@dataclass
class BenchmarkDataset:
    """In-memory representation of a benchmark CSV and its metadata."""

    csv_path: Path
    scenario: str
    title: str
    concurrency: List[int]
    rps: List[float]
    latencies: Dict[str, List[float]]
    metadata: Dict[str, str]

    @property
    def slug(self) -> str:
        return self.csv_path.stem


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Render throughput/latency charts from benchmark CSV files.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    parser.add_argument(
        "paths",
        nargs="*",
        type=Path,
        default=[Path("docs/benchmarks/data")],
        help="CSV file(s) or directories containing benchmark data.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("docs/benchmarks/charts"),
        help="Directory where generated charts (and optional Markdown) are written.",
    )
    parser.add_argument(
        "--formats",
        nargs="+",
        default=["png"],
        help="Image formats to render (passed to matplotlib).",
    )
    parser.add_argument(
        "--dpi",
        type=int,
        default=144,
        help="Image resolution in dots per inch.",
    )
    parser.add_argument(
        "--index",
        type=Path,
        help=(
            "Optional Markdown file (relative to --output unless absolute) that "
            "lists generated charts and metadata for MkDocs includes."
        ),
    )
    parser.add_argument(
        "--no-recursive",
        dest="recursive",
        action="store_false",
        help="Only inspect the top level of provided directories for CSV files.",
    )
    parser.set_defaults(recursive=True)
    return parser.parse_args()


def _warn(message: str) -> None:
    print(f"[plot-bench] WARNING: {message}", file=sys.stderr)


def discover_csv_files(paths: Sequence[Path], recursive: bool) -> List[Path]:
    csv_files: List[Path] = []
    for path in paths:
        if path.is_dir():
            iterator: Iterable[Path]
            iterator = path.rglob("*.csv") if recursive else path.glob("*.csv")
            for csv_path in iterator:
                if csv_path.is_file():
                    csv_files.append(csv_path)
        elif path.suffix.lower() == ".csv" and path.is_file():
            csv_files.append(path)
        else:
            _warn(f"Skipping {path} (not a CSV file or directory)")
    # Deduplicate while preserving order.
    seen: set[Path] = set()
    unique_files: List[Path] = []
    for csv_path in csv_files:
        resolved = csv_path.resolve()
        if resolved not in seen:
            seen.add(resolved)
            unique_files.append(csv_path)
    return sorted(unique_files)


def parse_float(value: str, *, allow_empty: bool = False) -> float | None:
    if value is None:
        return None
    text = value.strip()
    if not text:
        if allow_empty:
            return None
        raise ValueError("missing value")
    return float(text)


def load_metadata(csv_path: Path) -> Dict[str, str]:
    for candidate in (csv_path.with_suffix(".meta.json"), csv_path.with_suffix(".json")):
        if candidate.exists() and candidate.is_file():
            try:
                with candidate.open("r", encoding="utf-8") as meta_file:
                    payload = json.load(meta_file)
            except json.JSONDecodeError as exc:  # pragma: no cover - defensive
                raise ValueError(f"Invalid JSON in {candidate}: {exc}") from exc
            if not isinstance(payload, dict):
                raise ValueError(f"Metadata file {candidate} must contain a JSON object")
            converted: Dict[str, str] = {}
            for key, raw in payload.items():
                if raw is None:
                    continue
                if isinstance(raw, (str, int, float, bool)):
                    converted[key] = str(raw)
                else:
                    converted[key] = json.dumps(raw, sort_keys=True)
            return converted
    return {}


def load_dataset(csv_path: Path) -> BenchmarkDataset:
    with csv_path.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise ValueError("CSV file is missing a header row")
        rows: List[dict] = []
        latency_keys: set[str] = set()
        for index, row in enumerate(reader, start=2):  # header is line 1
            try:
                concurrency = int(row["concurrency"].strip())
            except (KeyError, AttributeError) as exc:
                raise ValueError("Required column 'concurrency' missing from CSV") from exc
            except ValueError as exc:
                raise ValueError(
                    f"Invalid concurrency value '{row.get('concurrency')}' at line {index}"
                ) from exc
            try:
                rps = parse_float(row["rps"])
            except KeyError as exc:
                raise ValueError("Required column 'rps' missing from CSV") from exc
            except ValueError as exc:
                raise ValueError(f"Invalid rps value '{row.get('rps')}' at line {index}") from exc
            data_row = {
                "concurrency": concurrency,
                "rps": rps,
            }
            scenario_value = row.get("scenario")
            if scenario_value:
                data_row["scenario"] = scenario_value.strip()
            for key, value in row.items():
                if key.startswith("latency_"):
                    latency_keys.add(key)
                    try:
                        parsed = parse_float(value, allow_empty=True)
                    except ValueError:
                        _warn(
                            f"Unable to parse {key!r}='{value}' in {csv_path} line {index}; field dropped"
                        )
                        parsed = None
                    data_row[key] = parsed
            rows.append(data_row)
    if not rows:
        raise ValueError("CSV contains no data rows")
    rows.sort(key=lambda item: item["concurrency"])
    concurrency = [row["concurrency"] for row in rows]
    rps = [float(row["rps"]) for row in rows]
    scenario_candidates = {row.get("scenario") for row in rows if row.get("scenario")}
    scenario = scenario_candidates.pop() if len(scenario_candidates) == 1 else csv_path.stem
    latencies: Dict[str, List[float]] = {}
    for key in latency_keys:
        values = [row.get(key) for row in rows]
        if any(value is None for value in values):
            _warn(
                f"Column {key!r} in {csv_path} has missing values; omitting from chart"
            )
            continue
        latencies[key] = [float(value) for value in values if value is not None]
    metadata = load_metadata(csv_path)
    title = metadata.get("title") or scenario.replace("-", " ").title()
    return BenchmarkDataset(
        csv_path=csv_path,
        scenario=scenario,
        title=title,
        concurrency=concurrency,
        rps=rps,
        latencies=latencies,
        metadata=metadata,
    )


def render_dataset(
    dataset: BenchmarkDataset, output_dir: Path, formats: Sequence[str], dpi: int
) -> Dict[str, Path]:
    output_dir.mkdir(parents=True, exist_ok=True)
    if not dataset.concurrency:
        raise ValueError(f"Dataset {dataset.csv_path} has no concurrency values")
    fig, axes = plt.subplots(1, 2, figsize=(12, 5))

    # Throughput plot
    axes[0].plot(dataset.concurrency, dataset.rps, marker="o", color="#0B6EFD")
    axes[0].set_title("Throughput")
    axes[0].set_xlabel("Concurrent clients")
    axes[0].set_ylabel("Requests / second")
    axes[0].grid(True, linestyle="--", alpha=0.4)

    # Latency plot
    if dataset.latencies:
        for key, values in sorted(dataset.latencies.items()):
            label = LATENCY_LABELS.get(key, key)
            axes[1].plot(dataset.concurrency, values, marker="o", label=label)
        axes[1].set_title("Latency")
        axes[1].set_xlabel("Concurrent clients")
        axes[1].set_ylabel("Milliseconds")
        axes[1].grid(True, linestyle="--", alpha=0.4)
        axes[1].legend()
    else:
        axes[1].set_visible(False)
        fig.set_size_inches(6, 5)

    fig.suptitle(dataset.title)

    if dataset.metadata:
        summary_keys = [
            "commit",
            "php_version",
            "openswoole_version",
            "wrk_version",
            "os",
            "hardware",
            "notes",
        ]
        summary_bits = [
            f"{key.replace('_', ' ').title()}: {dataset.metadata[key]}"
            for key in summary_keys
            if dataset.metadata.get(key)
        ]
        if summary_bits:
            caption = fill(" • ".join(summary_bits), width=100)
            fig.text(0.5, 0.02, caption, ha="center", va="bottom", fontsize=8)
    fig.tight_layout(rect=(0, 0.05, 1, 0.95))

    output_paths: Dict[str, Path] = {}
    for image_format in formats:
        suffix = image_format.lower().lstrip(".")
        output_path = output_dir / f"{dataset.slug}.{suffix}"
        fig.savefig(output_path, dpi=dpi, format=suffix)
        output_paths[suffix] = output_path
        print(f"[plot-bench] wrote {output_path}")
    plt.close(fig)
    return output_paths


def write_markdown_index(
    manifest: List[tuple[BenchmarkDataset, Dict[str, Path]]],
    output_dir: Path,
    index_path: Path,
    preferred_format: str,
) -> None:
    if not manifest:
        return
    if not index_path.is_absolute():
        index_path = output_dir / index_path
    index_path.parent.mkdir(parents=True, exist_ok=True)
    lines: List[str] = []
    lines.append("# Benchmark charts")
    lines.append("")
    lines.append(f"Generated on {datetime.utcnow().isoformat()}Z.")
    lines.append("")
    for dataset, outputs in manifest:
        lines.append(f"## {dataset.title}")
        lines.append("")
        chart_path = outputs.get(preferred_format)
        if chart_path is None and outputs:
            # Fallback to any available format.
            chart_path = next(iter(outputs.values()))
        if chart_path:
            rel_path = os.path.relpath(chart_path, index_path.parent)
            rel_path = rel_path.replace(os.sep, "/")
            lines.append(f"![{dataset.title}]({rel_path})")
            lines.append("")
        if dataset.metadata:
            lines.append("| Key | Value |")
            lines.append("| --- | ----- |")
            for key, value in sorted(dataset.metadata.items()):
                lines.append(f"| {key} | {value} |")
            lines.append("")
    index_path.write_text("\n".join(lines), encoding="utf-8")
    print(f"[plot-bench] wrote {index_path}")


def main() -> int:
    args = parse_args()
    csv_files = discover_csv_files(args.paths, recursive=args.recursive)
    if not csv_files:
        _warn("No CSV files discovered. Nothing to do.")
        return 1
    manifest: List[tuple[BenchmarkDataset, Dict[str, Path]]] = []
    for csv_path in csv_files:
        try:
            dataset = load_dataset(csv_path)
        except ValueError as exc:
            _warn(f"Skipping {csv_path}: {exc}")
            continue
        try:
            outputs = render_dataset(dataset, args.output, args.formats, args.dpi)
        except ValueError as exc:
            _warn(f"Failed to render {csv_path}: {exc}")
            continue
        manifest.append((dataset, outputs))
    if not manifest:
        _warn("No charts were generated.")
        return 1
    if args.index:
        write_markdown_index(manifest, args.output, args.index, args.formats[0])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
