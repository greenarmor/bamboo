#!/usr/bin/env python3
"""Plot Bamboo benchmark results (v1.0 documentation stub).

This placeholder script reserves the CLI interface for the upcoming
benchmark tooling. Replace the stub implementation with a fully fledged
plotting utility before the v1.0 release is tagged.
"""

from __future__ import annotations

import argparse
from pathlib import Path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Render charts from docs/benchmarks/data/ once the plotting "
            "tooling is implemented."
        )
    )
    parser.add_argument(
        "data_dir",
        nargs="?",
        default=Path("docs/benchmarks/data"),
        type=Path,
        help="Directory containing benchmark result files.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("docs/benchmarks"),
        help="Directory where generated charts should be written.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    print(
        "[plot-bench] Stub implementation. Expected to read data from"
        f" {args.data_dir} and write charts to {args.output}."
    )
    print(
        "Populate this script with matplotlib (or similar) chart generation "
        "before the v1.0 release candidate."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
