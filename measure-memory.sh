#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./measure-memory.sh hosting-kiddo-1
#   ./measure-memory.sh "$(docker compose ps -q kiddo)"

container="${1:?Usage: $0 <container-name-or-id>}"
pid_file="$(mktemp)"
trap 'rm -f "$pid_file"' EXIT

docker top "$container" -eo pid > "$pid_file"

printf "%-10s %12s %12s %12s\n" "PID" "RSS MiB" "PSS MiB" "Private MiB"

total_rss_kb=0
total_pss_kb=0
total_private_kb=0

while read -r pid; do
    status="/proc/$pid/status"
    smaps="/proc/$pid/smaps_rollup"

    [[ -r "$status" && -r "$smaps" ]] || continue

    rss_kb="$(awk '/^VmRSS:/ { print $2 }' "$status")"
    pss_kb="$(awk '/^Pss:/ { print $2 }' "$smaps")"
    private_kb="$(
        awk '
            /^Private_Clean:/ { total += $2 }
            /^Private_Dirty:/ { total += $2 }
            /^Private_Hugetlb:/ { total += $2 }
            END { print total + 0 }
        ' "$smaps"
    )"

    awk -v pid="$pid" \
        -v rss="$rss_kb" \
        -v pss="$pss_kb" \
        -v private="$private_kb" \
        'BEGIN {
            printf "%-10s %12.1f %12.1f %12.1f\n",
                pid, rss / 1024, pss / 1024, private / 1024
        }'

    total_rss_kb=$((total_rss_kb + rss_kb))
    total_pss_kb=$((total_pss_kb + pss_kb))
    total_private_kb=$((total_private_kb + private_kb))
done < <(tail -n +2 "$pid_file")

printf "%-10s %12.1f %12.1f %12.1f\n" \
    "TOTAL" \
    "$(awk -v value="$total_rss_kb" 'BEGIN { print value / 1024 }')" \
    "$(awk -v value="$total_pss_kb" 'BEGIN { print value / 1024 }')" \
    "$(awk -v value="$total_private_kb" 'BEGIN { print value / 1024 }')"
