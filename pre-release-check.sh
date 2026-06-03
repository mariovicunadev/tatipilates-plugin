#!/bin/zsh

set -u

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="/Users/vicunav/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin/bin/php"
ASSUME_YES=false

GREEN=$'\033[0;32m'
RED=$'\033[0;31m'
YELLOW=$'\033[0;33m'
BLUE=$'\033[0;34m'
RESET=$'\033[0m'

CHECK_NAMES=(
  "Sintaxis PHP"
  "BOM UTF-8"
  "Debug code"
  "Release desactualizado"
  "Version del plugin"
)

CHECK_STATUS=("pending" "pending" "pending" "pending" "pending")
CHECK_NOTES=("" "" "" "" "")

cd "$ROOT_DIR" || exit 1

for arg in "$@"; do
  case "$arg" in
    --yes)
      ASSUME_YES=true
      ;;
    *)
      echo "${RED}Argumento no reconocido:${RESET} $arg"
      echo "Uso: ./pre-release-check.sh [--yes]"
      exit 1
      ;;
  esac
done

mark_pass() {
  local index="$1"
  local note="${2:-OK}"
  CHECK_STATUS[$index]="pass"
  CHECK_NOTES[$index]="$note"
}

mark_fail() {
  local index="$1"
  local note="${2:-Fallo}"
  CHECK_STATUS[$index]="fail"
  CHECK_NOTES[$index]="$note"
}

mark_warn() {
  local index="$1"
  local note="${2:-Advertencia}"
  CHECK_STATUS[$index]="warn"
  CHECK_NOTES[$index]="$note"
}

print_summary() {
  echo
  echo "${BLUE}Resumen pre-release${RESET}"
  printf "%-28s | %-8s | %s\n" "Check" "Estado" "Detalle"
  printf "%-28s-+-%-8s-+-%s\n" "----------------------------" "--------" "------------------------------"

  local i check_status symbol color note
  for i in {1..5}; do
    check_status="${CHECK_STATUS[$i]}"
    note="${CHECK_NOTES[$i]}"

    case "$check_status" in
      pass)
        symbol="${GREEN}✓${RESET}"
        color="$GREEN"
        [[ -z "$note" ]] && note="OK"
        ;;
      fail)
        symbol="${RED}✗${RESET}"
        color="$RED"
        [[ -z "$note" ]] && note="Fallo"
        ;;
      warn)
        symbol="${YELLOW}✗${RESET}"
        color="$YELLOW"
        [[ -z "$note" ]] && note="Advertencia"
        ;;
      *)
        symbol="${YELLOW}✗${RESET}"
        color="$YELLOW"
        [[ -z "$note" ]] && note="No ejecutado"
        ;;
    esac

    printf "%-28s | %b%-8s%b | %s\n" "${CHECK_NAMES[$i]}" "$color" "$symbol" "$RESET" "$note"
  done
  echo
}

exit_with_summary() {
  print_summary
  exit 1
}

plugin_php_files() {
  find . \
    -path './release' -prune -o \
    -path './dev' -prune -o \
    -path './.git' -prune -o \
    -type f -name '*.php' -print | sort
}

echo "${BLUE}Pre-release check Tati Pilates${RESET}"
echo "Raiz: $ROOT_DIR"
echo

echo "${BLUE}CHECK 1 — Sintaxis PHP${RESET}"
if [[ ! -x "$PHP_BIN" ]]; then
  echo "${RED}No se encontro el PHP ejecutable:${RESET} $PHP_BIN"
  mark_fail 1 "PHP no encontrado"
  exit_with_summary
fi

php_errors=()
while IFS= read -r file; do
  lint_output="$("$PHP_BIN" -l "$file" 2>&1)"
  if [[ $? -ne 0 ]]; then
    php_errors+=("$file")
    echo "$lint_output"
  fi
done < <(plugin_php_files)

if [[ ${#php_errors[@]} -gt 0 ]]; then
  echo "${RED}Archivos con error de sintaxis:${RESET}"
  printf ' - %s\n' "${php_errors[@]}"
  mark_fail 1 "${#php_errors[@]} archivo(s) con error"
  exit_with_summary
fi
mark_pass 1 "Todos los .php pasan php -l"

echo "${BLUE}CHECK 2 — BOM UTF-8${RESET}"
bom_output="$(python3 - <<'PY'
from pathlib import Path

issues = []
for p in sorted(Path('.').rglob('*.php')):
    parts = set(p.parts)
    if 'release' in parts or 'dev' in parts or '.git' in parts:
        continue
    if p.read_bytes().startswith(b'\xef\xbb\xbf'):
        issues.append(f'BOM: {p}')

for issue in issues:
    print(issue)
PY
)"

if [[ -n "$bom_output" ]]; then
  echo "$bom_output"
  mark_fail 2 "BOM detectado"
  exit_with_summary
fi
mark_pass 2 "Sin BOM en archivos PHP"

echo "${BLUE}CHECK 3 — Debug code${RESET}"
debug_output="$(grep -RInE "var_dump\(|print_r\(|var_export\(|echo[[:space:]]+['\"]<pre|die\(['\"]debug['\"]\)|exit\(['\"]test['\"]\)" \
  --include='*.php' \
  --exclude-dir='release' \
  --exclude-dir='dev' \
  --exclude-dir='.git' \
  . 2>/dev/null || true)"

if [[ -n "$debug_output" ]]; then
  echo "$debug_output"
  mark_fail 3 "Debug code encontrado"
  exit_with_summary
fi
mark_pass 3 "Sin patrones de debug"

echo "${BLUE}CHECK 4 — Carpeta release desactualizada${RESET}"
if [[ -d "release/tatipilates" ]]; then
  echo "${YELLOW}Advertencia:${RESET} existe release/tatipilates/ como carpeta. Eliminala o regenerala antes del release."
  mark_warn 4 "Existe release/tatipilates/"
  exit_with_summary
fi
mark_pass 4 "No existe release/tatipilates/"

echo "${BLUE}CHECK 5 — Version del plugin${RESET}"
version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' tatipilates.php | head -n 1)"
if [[ -z "$version" ]]; then
  echo "${RED}No se pudo leer la linea Version: en tatipilates.php${RESET}"
  mark_fail 5 "Version no encontrada"
  exit_with_summary
fi

echo "Version detectada: $version"
if [[ "$ASSUME_YES" == true ]]; then
  echo "--yes activo: se asume que la version $version es correcta."
  answer="s"
else
  printf "¿La versión %s es correcta? (s/n) " "$version"
  read answer
fi

case "${answer:l}" in
  s|si|sí)
    mark_pass 5 "Version $version confirmada"
    ;;
  n|no)
    echo "Actualizá la versión en tatipilates.php antes de continuar."
    mark_fail 5 "Version no confirmada"
    exit_with_summary
    ;;
  *)
    echo "${RED}Respuesta no valida. Usá s o n.${RESET}"
    mark_fail 5 "Respuesta no valida"
    exit_with_summary
    ;;
esac

print_summary

if [[ "$ASSUME_YES" == true ]]; then
  echo "--yes activo: se generara el ZIP automaticamente."
  zip_answer="s"
else
  printf "¿Generar ZIP? (s/n) "
  read zip_answer
fi

case "${zip_answer:l}" in
  s|si|sí)
    if ! command -v zip >/dev/null 2>&1; then
      echo "${RED}No se encontro el comando zip.${RESET}"
      exit 1
    fi

    mkdir -p release
    timestamp="$(date +%Y%m%d-%H%M)"
    zip_path="$ROOT_DIR/release/tatipilates-$timestamp.zip"
    tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/tatipilates-release.XXXXXX")"

    mkdir -p "$tmp_dir/tatipilates"
    cp -R tatipilates.php uninstall.php includes admin public assets "$tmp_dir/tatipilates/"
    find "$tmp_dir/tatipilates" -name '.DS_Store' -delete
    find "$tmp_dir/tatipilates" -name '.*' -depth -exec rm -rf {} +

    (
      cd "$tmp_dir" || exit 1
      zip -qr "$zip_path" tatipilates
    )
    zip_status=$?
    rm -rf "$tmp_dir"

    if [[ $zip_status -ne 0 ]]; then
      echo "${RED}No se pudo generar el ZIP.${RESET}"
      exit 1
    fi

    echo "${GREEN}ZIP generado:${RESET} $zip_path"
    ;;
  n|no|"")
    echo "ZIP no generado."
    ;;
  *)
    echo "${RED}Respuesta no valida. Usá s o n.${RESET}"
    exit 1
    ;;
esac
