#!/usr/bin/env bash
# Xenarch Edge sandbox — bot-gating E2E matrix (XEN-464).
# Mirrors xenarch-plugins/tests/bot-detection-test-prompt.md for the edge Worker.
#
# Usage: ./test.sh [BASE_URL]   (default https://edge.xenarch.dev)
set -u

BASE="${1:-https://edge.xenarch.dev}"
GATED="/dispatch/the-toll-economy/"
FREE="/"
BROWSER="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"

pass=0; fail=0

check() {
  local desc="$1" ua="$2" path="$3" want="$4"
  local cb="?cb=$(date +%s%N)"
  local code
  code=$(curl -s -o /dev/null -w "%{http_code}" -A "$ua" "$BASE$path$cb")
  if [ "$code" = "$want" ]; then
    printf "  \033[32mPASS\033[0m  %-44s %s (got %s)\n" "$desc" "$path" "$code"; pass=$((pass+1))
  else
    printf "  \033[31mFAIL\033[0m  %-44s %s (got %s, want %s)\n" "$desc" "$path" "$code" "$want"; fail=$((fail+1))
  fi
}

echo "Testing $BASE"
echo "--- humans read free ---"
check "browser → landing"          "$BROWSER"           "$FREE"  200
check "browser → gated dispatch"   "$BROWSER"           "$GATED" 200
echo "--- free path is free for everyone ---"
check "GPTBot → landing (free)"    "GPTBot/1.0"         "$FREE"  200
echo "--- agents pay on gated content ---"
check "GPTBot → gated"             "GPTBot/1.0"         "$GATED" 402
check "ClaudeBot → gated"          "ClaudeBot/1.0"      "$GATED" 402
check "python-requests → gated"    "python-requests/2.31" "$GATED" 402
check "empty UA → gated (fail-open)" ""                 "$GATED" 200
echo "--- search crawlers allowed by default ---"
check "Googlebot → gated"          "Googlebot/2.1"      "$GATED" 200

echo
echo "Inspect the 402 envelope an agent receives:"
echo "  curl -s -A GPTBot $BASE$GATED | jq ."
echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
