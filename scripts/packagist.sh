#!/usr/bin/env bash
set -euo pipefail

PACKAGIST_API_BASE="https://packagist.org/api"
PACKAGIST_WEB_BASE="https://packagist.org/packages"
PACKAGE_REPOSITORY="https://github.com/kugarocks/bookstack-content-sync"
PACKAGE_NAME="kugarocks/bookstack-content-sync"

if [[ -t 1 ]]; then
  COLOR_BLUE='\033[1;34m'
  COLOR_GREEN='\033[1;32m'
  COLOR_YELLOW='\033[1;33m'
  COLOR_RED='\033[1;31m'
  COLOR_BOLD='\033[1m'
  COLOR_CYAN='\033[1;36m'
  COLOR_MAGENTA='\033[1;35m'
  COLOR_DIM='\033[2m'
  COLOR_RESET='\033[0m'
else
  COLOR_BLUE=''
  COLOR_GREEN=''
  COLOR_YELLOW=''
  COLOR_RED=''
  COLOR_BOLD=''
  COLOR_CYAN=''
  COLOR_MAGENTA=''
  COLOR_DIM=''
  COLOR_RESET=''
fi

print_blank() {
  printf '\n'
}

fmt_cmd() {
  printf '%b' "${COLOR_CYAN}$1${COLOR_RESET}"
}

fmt_arg() {
  printf '%b' "${COLOR_YELLOW}$1${COLOR_RESET}"
}

fmt_meta() {
  printf '%b' "${COLOR_MAGENTA}$1${COLOR_RESET}"
}

fmt_env() {
  printf '%b' "${COLOR_GREEN}$1${COLOR_RESET}"
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
  printf '%b\n' "${COLOR_BOLD}Packagist Helper${COLOR_RESET}"
  printf '%b\n' "${COLOR_DIM}Create, refresh, and verify the Packagist package entry.${COLOR_RESET}"
  print_blank

  printf '%b\n' "${COLOR_BLUE}Usage${COLOR_RESET}"
  printf '  %s %s %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'publish')" \
    "$(fmt_arg '[--username USERNAME]')" \
    "$(fmt_arg '[--token TOKEN]')"
  printf '  %s %s %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'create')" \
    "$(fmt_arg '[--username USERNAME]')" \
    "$(fmt_arg '[--token TOKEN]')"
  printf '  %s %s %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'update')" \
    "$(fmt_arg '[--username USERNAME]')" \
    "$(fmt_arg '[--token TOKEN]')"
  printf '  %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'check')"
  printf '  %s %s\n' "$(fmt_cmd 'scripts/packagist.sh')" "$(fmt_cmd 'help')"
  print_blank

  printf '%b\n' "${COLOR_BLUE}Environment${COLOR_RESET}"
  printf '  %s   %s\n' "$(fmt_env 'COMPOSER_PACKAGIST_USERNAME')" 'Packagist username'
  printf '  %s      %s\n' "$(fmt_env 'COMPOSER_PACKAGIST_TOKEN')" 'Packagist API token'
  print_blank

  printf '%b\n' "${COLOR_BLUE}Examples${COLOR_RESET}"
  printf '  %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'publish')"
  printf '  %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'create')"
  printf '  %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'update')"
  printf '  %s %s\n' \
    "$(fmt_cmd 'scripts/packagist.sh')" \
    "$(fmt_cmd 'check')"
  print_blank

  printf '%b\n' "${COLOR_BLUE}Defaults${COLOR_RESET}"
  printf '  %s   %s\n' "$(fmt_meta 'Repository')" "$(fmt_meta "${PACKAGE_REPOSITORY}")"
  printf '  %s      %s\n' "$(fmt_meta 'Package')" "$(fmt_meta "${PACKAGE_NAME}")"
  print_blank
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    error "Required command not found: $1"
    exit 1
  fi
}

require_value() {
  local name="$1"
  local value="$2"
  if [[ -z "$value" ]]; then
    error "Missing required value: $name"
    usage
    exit 1
  fi
}

json_escape() {
  python3 - <<'PY' "$1"
import json
import sys
print(json.dumps(sys.argv[1]))
PY
}

api_request() {
  local endpoint="$1"
  local payload="$2"
  local username="$3"
  local token="$4"
  local tmp
  tmp="$(mktemp)"
  local http_code
  http_code="$(curl -sS -o "$tmp" -w '%{http_code}' \
    -X POST \
    -H 'Content-Type: application/json' \
    -H "Authorization: Bearer ${username}:${token}" \
    "${PACKAGIST_API_BASE}/${endpoint}" \
    -d "$payload")"
  local body
  body="$(cat "$tmp")"
  rm -f "$tmp"
  printf '%s\n%s' "$http_code" "$body"
}

looks_like_duplicate_create() {
  local body="$1"
  local lowered
  lowered="$(printf '%s' "$body" | tr '[:upper:]' '[:lower:]')"
  [[ "$lowered" == *"already"* ]] && [[ "$lowered" == *"package"* || "$lowered" == *"repository"* ]]
}

create_package() {
  local repository="$1"
  local username="$2"
  local token="$3"
  local payload
  payload="{\"repository\":$(json_escape "$repository")}" 

  info "Creating package on Packagist"
  local response
  response="$(api_request 'create-package' "$payload" "$username" "$token")"
  local http_code body
  http_code="$(printf '%s' "$response" | sed -n '1p')"
  body="$(printf '%s' "$response" | sed '1d')"

  if [[ "$http_code" =~ ^2 ]]; then
    success "Package create request accepted"
    return 0
  fi

  if looks_like_duplicate_create "$body"; then
    warn "Package appears to be already registered on Packagist; continuing"
    return 0
  fi

  error "Packagist create-package failed with HTTP ${http_code}"
  printf '%s\n' "$body" >&2
  return 1
}

update_package() {
  local repository="$1"
  local username="$2"
  local token="$3"
  local payload
  payload="{\"repository\":$(json_escape "$repository")}" 

  info "Requesting Packagist package update"
  local response
  response="$(api_request 'update-package' "$payload" "$username" "$token")"
  local http_code body
  http_code="$(printf '%s' "$response" | sed -n '1p')"
  body="$(printf '%s' "$response" | sed '1d')"

  if [[ "$http_code" =~ ^2 ]]; then
    success "Package update request accepted"
    return 0
  fi

  error "Packagist update-package failed with HTTP ${http_code}"
  printf '%s\n' "$body" >&2
  return 1
}

check_package_page() {
  local package_name="$1"
  local url="${PACKAGIST_WEB_BASE}/${package_name}"
  info "Checking Packagist package page"

  if curl -fsS "$url" >/dev/null; then
    success "Package page is reachable: $url"
    return 0
  fi

  error "Package page is not reachable yet: $url"
  return 1
}

main() {
  require_command curl
  require_command python3

  local command="${1:-help}"
  shift || true

  local repository="$PACKAGE_REPOSITORY"
  local package_name="$PACKAGE_NAME"
  local username="${COMPOSER_PACKAGIST_USERNAME:-}"
  local token="${COMPOSER_PACKAGIST_TOKEN:-}"

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --username)
        username="${2:-}"
        shift 2
        ;;
      --token)
        token="${2:-}"
        shift 2
        ;;
      -h|--help)
        usage
        exit 0
        ;;
      *)
        error "Unknown argument: $1"
        usage
        exit 1
        ;;
    esac
  done

  case "$command" in
    help|'')
      usage
      ;;
    check)
      print_blank
      require_value '--package' "$package_name"
      check_package_page "$package_name"
      print_blank
      ;;
    create)
      print_blank
      require_value '--repository' "$repository"
      require_value 'COMPOSER_PACKAGIST_USERNAME/--username' "$username"
      require_value 'COMPOSER_PACKAGIST_TOKEN/--token' "$token"
      create_package "$repository" "$username" "$token"
      print_blank
      ;;
    update)
      print_blank
      require_value '--repository' "$repository"
      require_value 'COMPOSER_PACKAGIST_USERNAME/--username' "$username"
      require_value 'COMPOSER_PACKAGIST_TOKEN/--token' "$token"
      update_package "$repository" "$username" "$token"
      print_blank
      ;;
    publish)
      print_blank
      require_value '--repository' "$repository"
      require_value '--package' "$package_name"
      require_value 'COMPOSER_PACKAGIST_USERNAME/--username' "$username"
      require_value 'COMPOSER_PACKAGIST_TOKEN/--token' "$token"
      create_package "$repository" "$username" "$token"
      check_package_page "$package_name" || warn 'Package page is not reachable yet; update will still be requested'
      update_package "$repository" "$username" "$token"
      warn 'If the package page exists but the new version is still missing, wait a moment and run update again.'
      print_blank
      ;;
    *)
      error "Unknown command: $command"
      usage
      exit 1
      ;;
  esac
}

main "$@"
