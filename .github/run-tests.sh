#!/usr/bin/env bash
# Run the suite in parallel. Some dependency sets resolve a Pest/ParaTest pair that cannot start parallel workers
# (ArgumentCountError in WrapperWorker); only then fall back to a serial run. A failing test never triggers the fallback.
set -o pipefail
log=$(mktemp)
vendor/bin/pest --parallel 2>&1 | tee "$log"
status=${PIPESTATUS[0]}
if [ "$status" -ne 0 ] && grep -q "WrapperWorker::__construct" "$log"; then
  echo "::warning::Parallel runner is incompatible with this dependency set; running serially."
  exec vendor/bin/pest
fi
exit "$status"
