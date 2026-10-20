#!/usr/bin/env bash
#
# Skrip backup database untuk PRD-08 Production Readiness.
#
# Membuat backup terenkripsi dari database SMS, menyimpan di lokasi terpisah,
# dan mencatat hasilnya untuk uji restore di environment terisolasi.
#
# Penggunaan:
#   ./scripts/backup-db.sh
#
# Variabel lingkungan (diatur dari .env atau sistem):
#   DB_CONNECTION, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_HOST, DB_PORT
#   BACKUP_DIR (default: /var/backups/sms)
#   ENCRYPTION_KEY (opsional, untuk enkripsi file backup)

set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/sms}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/sms_${TIMESTAMP}.sql.gz"
ENCRYPTED_FILE="${BACKUP_FILE}.enc"
LOG_FILE="${BACKUP_DIR}/backup_${TIMESTAMP}.log"

mkdir -p "${BACKUP_DIR}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"
}

log "Memulai backup database SMS..."

# Periksa ketersediaan perintah yang dibutuhkan.
for cmd in mysqldump sqlite3 openssl gzip; do
    if ! command -v "${cmd}" >/dev/null 2>&1; then
        log "ERROR: Perintah '${cmd}' tidak ditemukan. Backup dibatalkan."
        exit 1
    fi
done

# Buat backup database.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-database/database.sqlite}"
    if [ ! -f "${DB_PATH}" ]; then
        log "ERROR: File database SQLite tidak ditemukan: ${DB_PATH}"
        exit 1
    fi
    cp "${DB_PATH}" "${BACKUP_FILE}"
    log "Backup SQLite dibuat: ${BACKUP_FILE}"
else
    mysqldump --single-transaction --routines --triggers \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME}" \
        -p"${DB_PASSWORD}" \
        "${DB_DATABASE}" | gzip > "${BACKUP_FILE}"
    log "Backup MySQL dibuat: ${BACKUP_FILE}"
fi

# Enkripsi jika ENCRYPTION_KEY diatur.
if [ -n "${ENCRYPTION_KEY:-}" ]; then
    openssl enc -aes-256-cbc -salt -pbkdf2 \
        -in "${BACKUP_FILE}" -out "${ENCRYPTED_FILE}" -pass pass:"${ENCRYPTION_KEY}"
    rm -f "${BACKUP_FILE}"
    log "Backup dienkripsi: ${ENCRYPTED_FILE}"
    log "Catatan: Simpan ENCRYPTION_KEY di tempat aman, terpisah dari lokasi backup."
else
    log "PERINGATAN: ENCRYPTION_KEY tidak diatur. Backup tidak dienkripsi."
fi

log "Backup selesai. File tersimpan di: ${BACKUP_DIR}"
log "Uji restore harus dilakukan di environment terisolasi, bukan produksi."
