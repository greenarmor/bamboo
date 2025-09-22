#!/usr/bin/env bash
# Print an export statement that prepends the repository's bin directory to PATH.
# Invoke via `eval "$(./bootstrap/shell-init.sh)"` to ensure plain `composer`
# resolves to the bundled wrapper that suppresses PHP 8.4 deprecation notices.
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)
ROOT_DIR=$(cd "${SCRIPT_DIR}/.." && pwd -P)
TARGET_PATH="${ROOT_DIR}/bin"
PATH_VALUE=${PATH-}

PATH_SEP=':'
case "${OSTYPE-}" in
  cygwin*|msys*|win32*)
    PATH_SEP=';'
    ;;
  *)
    ;;
esac

PATH_ENTRIES=()
IFS="${PATH_SEP}" read -r -a PATH_ENTRIES <<< "${PATH_VALUE}" || PATH_ENTRIES=()
FILTERED_ENTRIES=()
for ENTRY in "${PATH_ENTRIES[@]}"; do
  [[ -z "${ENTRY}" ]] && continue
  # Normalise potential trailing separators to compare accurately.
  if [[ "${ENTRY%/}" == "${TARGET_PATH%/}" ]]; then
    continue
  fi
  FILTERED_ENTRIES+=("${ENTRY}")
done

JOINED_REMAINDER=""
for ENTRY in "${FILTERED_ENTRIES[@]}"; do
  if [[ -z "${JOINED_REMAINDER}" ]]; then
    JOINED_REMAINDER="${ENTRY}"
  else
    JOINED_REMAINDER+="${PATH_SEP}${ENTRY}"
  fi
done

if [[ -n "${JOINED_REMAINDER}" ]]; then
  NEW_PATH="${TARGET_PATH}${PATH_SEP}${JOINED_REMAINDER}"
else
  NEW_PATH="${TARGET_PATH}"
fi

printf 'export PATH=%q\n' "${NEW_PATH}"
printf 'hash -r\n'
