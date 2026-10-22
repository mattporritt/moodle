#!/bin/bash
# build-fixture.sh <moodle-5.3-tree> [fixture-dir]
#
# Builds the Moodle 5.3 fixture: installs the given 5.3 tree into a scratch database, seeds it with the LTI data in
# fixture/seed_fixture.php, and writes fixture-dir/fixture.dump (pg_dump -Fc) and fixture-dir/moodledata.tgz.
# restore-fixture.sh turns those back into a database and data directory for any number of upgrade runs.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
TREE="$1"; OUT="${2:-$HERE/../fixture-build}"
mkdir -p "$OUT"; OUT="$(cd "$OUT" && pwd)"
DB=ltip_fixture_build; DATA=fixture-build
LTIP_DATA="${LTIP_DATA:-$HOME/lti-parity-data}"

"$HERE/mkdb" $DB
rm -rf "$LTIP_DATA/$DATA"
"$HERE/ltip-run" "$TREE" $DB $DATA php admin/cli/install_database.php --agree-license --fullname=ltix --shortname=ltix \
  --adminuser=admin --adminpass=test --adminemail=admin@example.com
"$HERE/ltip-run" "$TREE" $DB $DATA php /ltip/fixture/seed_fixture.php

"$HERE/db" pg_dump -Fc --no-owner -d $DB > "$OUT/fixture.dump"
tar -C "$LTIP_DATA/$DATA" -czf "$OUT/moodledata.tgz" moodledata
echo "fixture written to $OUT (fixture.dump, moodledata.tgz); scratch database $DB can be dropped"
