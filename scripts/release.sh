#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/release.sh check [tag]
  scripts/release.sh tag <tag>
  scripts/release.sh push <tag>
  scripts/release.sh all <tag>

Examples:
  scripts/release.sh check v0.1.0
  scripts/release.sh tag v0.1.0
  scripts/release.sh push v0.1.0
  scripts/release.sh all v0.1.0
USAGE
}

require_clean_tree() {
  if [[ -n "$(git status --short)" ]]; then
    echo "Working tree is not clean" >&2
    git status --short >&2
    exit 1
  fi
}

run_checks() {
  local tag="${1:-v0.1.0}"
  echo "==> Checking working tree"
  require_clean_tree

  echo "==> Running Composer validation"
  composer validate --strict

  echo "==> Running test suite"
  composer test

  echo "==> Reviewing release notes hint"
  echo "Review docs/release-notes-${tag}.md if it exists, or update the matching release notes file manually."
}

create_tag() {
  local tag="$1"
  require_clean_tree
  if git rev-parse -q --verify "refs/tags/${tag}" >/dev/null; then
    echo "Tag ${tag} already exists" >&2
    exit 1
  fi

  git tag -a "${tag}" -m "Release ${tag}"
  git show "${tag}" --stat --no-patch
}

push_release() {
  local tag="$1"
  git push origin main
  git push origin "${tag}"
}

main() {
  local command="${1:-}"
  case "${command}" in
    check)
      run_checks "${2:-v0.1.0}"
      ;;
    tag)
      [[ $# -eq 2 ]] || { usage; exit 1; }
      create_tag "$2"
      ;;
    push)
      [[ $# -eq 2 ]] || { usage; exit 1; }
      push_release "$2"
      ;;
    all)
      [[ $# -eq 2 ]] || { usage; exit 1; }
      run_checks "$2"
      create_tag "$2"
      push_release "$2"
      ;;
    *)
      usage
      exit 1
      ;;
  esac
}

main "$@"
