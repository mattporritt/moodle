#!/bin/bash
# restore-fixture.sh <fixture-dir> <db> <data> : recreate the seeded 5.3 site in a fresh database and data directory
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
FIX="$(cd "$1" && pwd)"; DB="$2"; DATA="$3"
LTIP_DATA="${LTIP_DATA:-$HOME/lti-parity-data}"
"$HERE/mkdb" "$DB"
"$HERE/db" pg_restore --no-owner -d "$DB" < "$FIX/fixture.dump"
rm -rf "$LTIP_DATA/$DATA"; mkdir -p "$LTIP_DATA/$DATA"
tar -C "$LTIP_DATA/$DATA" -xzf "$FIX/moodledata.tgz"
mkdir -p "$LTIP_DATA/$DATA"/moodledata/{cache,localcache,temp,muc}
chmod -R 777 "$LTIP_DATA/$DATA"
echo "restored $DB / $LTIP_DATA/$DATA"
