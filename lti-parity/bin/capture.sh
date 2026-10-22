#!/bin/bash
# capture.sh <tree> <fixture-dir> <label>
#
# Restores the 5.3 fixture, upgrades it with <tree>, and records the launch data of every LTI activity as teacher1 and
# student1 into out/<label>.json (normalised) plus out/<label>.{raw,verify,notices,meta}.json. Run it once per tree
# (BASE for the reference, then each branch) and compare the .json files with compare/compare_launch.py.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
TREE="$1"; FIX="$2"; L="$3"
DB="ltip_$L"; DATA="$L"
"$HERE/restore-fixture.sh" "$FIX" "$DB" "$DATA"
"$HERE/ltip-run" "$TREE" "$DB" "$DATA" php admin/cli/upgrade.php --non-interactive --allow-unstable
"$HERE/ltip-run" "$TREE" "$DB" "$DATA" php /ltip/capture/capture_launch.php --label="$L" --out="/ltip/out/$L.json" \
  --sidecar="/ltip/out/$L"
python3 "$HERE/../compare/verify_summary.py" "$HERE/../out/$L.verify.json"
