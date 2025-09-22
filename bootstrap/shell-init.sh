#!/usr/bin/env bash
# Print an export statement that prepends the repository's bin directory to PATH.
# Invoke via `eval "$(./bootstrap/shell-init.sh)"` to ensure plain `composer`
# resolves to the bundled wrapper that suppresses PHP 8.4 deprecation notices.
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT_DIR=$(cd "${SCRIPT_DIR}/.." && pwd)
TARGET_PATH="${ROOT_DIR}/bin"

case ":${PATH}:" in
  *:"${TARGET_PATH}":*)
    printf '# PATH already includes %s\n' "${TARGET_PATH}"
    ;;
  *)
    printf 'export PATH=%q\n' "${TARGET_PATH}:${PATH}"
    ;;
esac
