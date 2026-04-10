#!/usr/bin/env bash
set -euo pipefail

if [[ -t 1 ]]; then
  COLOR_BLUE='\033[1;34m'
  COLOR_GREEN='\033[1;32m'
  COLOR_YELLOW='\033[1;33m'
  COLOR_RED='\033[1;31m'
  COLOR_BOLD='\033[1m'
  COLOR_RESET='\033[0m'
else
  COLOR_BLUE=''
  COLOR_GREEN=''
  COLOR_YELLOW=''
  COLOR_RED=''
  COLOR_BOLD=''
  COLOR_RESET=''
fi

print_blank() {
  printf '\n'
}

info() {
  printf '%b\n' "${COLOR_BLUE}==>${COLOR_RESET} $*"
}

success() {
  printf '%b\n' "${COLOR_GREEN}OK${COLOR_RESET} $*"
}

warn() {
  printf '%b\n' "${COLOR_YELLOW}WARN${COLOR_RESET} $*"
}

error() {
  printf '%b\n' "${COLOR_RED}ERROR${COLOR_RESET} $*" >&2
}

usage() {
  print_blank
  printf '%b\n' "${COLOR_BOLD}Release Helper${COLOR_RESET}"
  printf '%b\n' "${COLOR_BLUE}Usage${COLOR_RESET}"
  cat <<'USAGE'
  scripts/release.sh check [tag]
  scripts/release.sh tag <tag>
  scripts/release.sh push <tag>
  scripts/release.sh all <tag>
USAGE
  print_blank
  printf '%b\n' "${COLOR_BLUE}Examples${COLOR_RESET}"
  cat <<'EXAMPLES'
  scripts/release.sh check v0.1.0
  scripts/release.sh tag v0.1.0
  scripts/release.sh push v0.1.0
  scripts/release.sh all v0.1.0
EXAMPLES
  print_blank
}

release_notes_path() {
  local tag="$1"
  echo "docs/release/notes-${tag}.md"
}

require_clean_tree() {
  if [[ -n "$(git status --short)" ]]; then
    error "Working tree is not clean"
    git status --short >&2
    exit 1
  fi
}

require_release_notes() {
  local tag="$1"
  local notes_path
  notes_path="$(release_notes_path "$tag")"

  if [[ ! -f "$notes_path" ]]; then
    error "Expected release notes file not found: $notes_path"
    exit 1
  fi
}

run_checks() {
  local tag="${1:-v0.1.0}"
  local notes_path
  notes_path="$(release_notes_path "$tag")"

  print_blank
  info "Checking working tree"
  require_clean_tree
  success "Working tree is clean"

  info "Checking release notes"
  require_release_notes "$tag"
  success "Using release notes: $notes_path"

  info "Running Composer validation"
  composer validate --strict

  info "Running test suite"
  composer test

  info "Release notes preview"
  sed -n '1,80p' "$notes_path"
  print_blank
}

create_tag() {
  local tag="$1"
  print_blank
  require_clean_tree
  require_release_notes "$tag"

  if git rev-parse -q --verify "refs/tags/${tag}" >/dev/null; then
    error "Tag ${tag} already exists"
    exit 1
  fi

  info "Creating annotated tag ${tag}"
  git tag -a "${tag}" -m "Release ${tag}"
  git show "${tag}" --stat --no-patch
  success "Created tag ${tag}"
  print_blank
}

push_release() {
  local tag="$1"
  print_blank
  if ! git rev-parse -q --verify "refs/tags/${tag}" >/dev/null; then
    error "Tag ${tag} does not exist locally"
    exit 1
  fi

  info "Pushing main"
  git push origin main
  info "Pushing tag ${tag}"
  git push origin "${tag}"
  success "Pushed main and ${tag}"
  print_blank
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
    -h|--help|help|'')
      usage
      ;;
    *)
      error "Unknown command: $command"
      usage
      exit 1
      ;;
  esac
}

main "$@"
