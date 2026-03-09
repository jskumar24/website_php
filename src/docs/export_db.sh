#!/bin/bash
# TS-12 Database Export Script
# Run this before deploying to production
# Usage: bash export_db.sh

DB_USER="root"
DB_PASS="root123"
DB_NAME="attendance_db"
OUTPUT="ts12_backup_$(date +%Y%m%d_%H%M%S).sql"

echo "Exporting $DB_NAME to $OUTPUT ..."
mysqldump -u "$DB_USER" -p"$DB_PASS" \
  --single-transaction \
  --routines \
  --triggers \
  --add-drop-table \
  "$DB_NAME" > "$OUTPUT"

if [ $? -eq 0 ]; then
  echo "SUCCESS — saved as: $OUTPUT"
  echo "File size: $(du -sh $OUTPUT | cut -f1)"
else
  echo "ERROR — export failed. Is MySQL running?"
fi
