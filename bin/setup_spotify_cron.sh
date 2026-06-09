#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="$(command -v php)"
LOG_FILE="${HOME}/spotify_update.log"
CRON_LINE="15 8 * * * cd ${ROOT_DIR} && ${PHP_BIN} ${ROOT_DIR}/atualizar_spotify.php >> ${LOG_FILE} 2>&1"
JSON_FILES=(
  "${ROOT_DIR}/top30.json"
  "${ROOT_DIR}/top30_com_imagem.json"
  "${ROOT_DIR}/top30_display.json"
  "${ROOT_DIR}/historico_artistas.json"
)

echo "Preparando arquivos do Spotify em ${ROOT_DIR}"
sudo touch "${JSON_FILES[@]}"
sudo chown admin:www-data "${JSON_FILES[@]}"
sudo chmod 664 "${JSON_FILES[@]}"

echo "Testando atualização manual..."
"${PHP_BIN}" "${ROOT_DIR}/atualizar_spotify.php"

echo "Configurando atualização diária às 08:15..."
current_cron="$(mktemp)"
crontab -l 2>/dev/null > "${current_cron}" || true

if ! grep -Fq "${ROOT_DIR}/atualizar_spotify.php" "${current_cron}"; then
  printf '%s\n' "${CRON_LINE}" >> "${current_cron}"
  crontab "${current_cron}"
else
  echo "Cron do Spotify já existia; não dupliquei."
fi

rm -f "${current_cron}"

echo "Pronto. Log diário: ${LOG_FILE}"
