# Browser Proof Test Prompt

Copy the prompt below into any AI coding agent and let it run against a deployed Xenarch WordPress gate.

---

## Prompt

Read the test script at `xenarch-plugins/tests/browser-proof-test-prompt.md` in this repo. Extract the bash script from the code block, save it to a temp file, make it executable, and run it. Report the results in a table with columns:

- Case
- Expected
- HTTP
- `X-Xenarch-Decision`
- `X-Xenarch-Bot`
- Pass/Fail

Important:
- This gate now has three outcomes: `allow`, `block`, and `challenge`.
- `curl` cannot execute the browser-proof challenge, so high-fidelity browser requests without proof should usually get `403` with `X-Xenarch-Decision: challenge`.
- Search crawlers should still get `200`.
- Known non-human traffic should get `402` with `X-Xenarch-Decision: block`.
- Suspicious browser-like traffic without proof must never receive the protected content body.

Run all tests and clearly highlight failures.

```bash
#!/bin/bash
set -euo pipefail

URL="https://gate.xenarch.dev/sample-page/"
PASS=0
FAIL=0

run_case() {
  local label="$1"
  local expected_http="$2"
  local expected_decision="$3"
  shift 3

  local headers_file body_file
  headers_file="$(mktemp)"
  body_file="$(mktemp)"

  curl -sS -D "$headers_file" -o "$body_file" "$@" "$URL"

  local http decision bot body_snippet
  http="$(awk 'toupper($1) ~ /^HTTP\// {code=$2} END {print code}' "$headers_file")"
  decision="$(awk -F': ' 'tolower($1)=="x-xenarch-decision" {gsub("\r","",$2); print $2}' "$headers_file" | tail -n 1)"
  bot="$(awk -F': ' 'tolower($1)=="x-xenarch-bot" {gsub("\r","",$2); print $2}' "$headers_file" | tail -n 1)"
  body_snippet="$(tr '\n' ' ' < "$body_file" | cut -c1-160)"

  if [[ "$http" == "$expected_http" && "$decision" == "$expected_decision" ]]; then
    echo "PASS | $label | http=$http | decision=${decision:-<none>} | bot=${bot:-<none>}"
    PASS=$((PASS+1))
  else
    echo "FAIL | $label | expected_http=$expected_http expected_decision=$expected_decision | got_http=${http:-<none>} got_decision=${decision:-<none>} | bot=${bot:-<none>}"
    echo "BODY  | $body_snippet"
    FAIL=$((FAIL+1))
  fi

  rm -f "$headers_file" "$body_file"
}

echo "========================================"
echo " BROWSER PROOF TEST SUITE"
echo " Target: $URL"
echo " $(date -u)"
echo "========================================"

echo ""
echo "── Known Non-Human Traffic (expect block/402) ──"
run_case "GPTBot" "402" "block" -H "User-Agent: GPTBot/1.0"
run_case "python-requests" "402" "block" -H "User-Agent: python-requests/2.31.0"
run_case "Twitterbot" "402" "block" -H "User-Agent: Twitterbot/1.0"
run_case "Playwright" "402" "block" -H "User-Agent: Playwright/1.40"

echo ""
echo "── Browser-Like Without Proof (expect challenge/403) ──"
run_case "Chrome full headers no proof" "403" "challenge" \
  -H "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36" \
  -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  -H "Accept-Language: en-US,en;q=0.9" \
  -H "Accept-Encoding: gzip, deflate, br" \
  -H "Sec-Fetch-Mode: navigate" \
  -H "Sec-Fetch-Site: none" \
  -H "Sec-Fetch-Dest: document" \
  -H "Sec-Fetch-User: ?1" \
  -H "Sec-Ch-Ua: \"Chromium\";v=\"122\", \"Google Chrome\";v=\"122\"" \
  -H "Sec-CH-UA-Mobile: ?0" \
  -H "Sec-CH-UA-Platform: \"macOS\"" \
  -H "Upgrade-Insecure-Requests: 1"

run_case "Firefox full headers no proof" "403" "challenge" \
  -H "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:123.0) Gecko/20100101 Firefox/123.0" \
  -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  -H "Accept-Language: en-US,en;q=0.5" \
  -H "Accept-Encoding: gzip, deflate, br" \
  -H "Sec-Fetch-Mode: navigate" \
  -H "Sec-Fetch-Site: none" \
  -H "Sec-Fetch-Dest: document" \
  -H "Sec-Fetch-User: ?1" \
  -H "Upgrade-Insecure-Requests: 1"

echo ""
echo "── Trusted Search Crawlers (expect allow/200) ──"
run_case "Googlebot" "200" "" -H "User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
run_case "Bingbot" "200" "" -H "User-Agent: Mozilla/5.0 (compatible; Bingbot/2.0; +http://www.bing.com/bingbot.htm)"
run_case "DuckDuckBot" "200" "" -H "User-Agent: Mozilla/5.0 (compatible; DuckDuckBot/1.1; https://duckduckgo.com/duckduckbot)"

echo ""
echo "========================================"
echo " RESULTS"
echo "========================================"
echo " Passed: $PASS"
echo " Failed: $FAIL"
echo " Total:  $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "ALL TESTS PASSED"
else
  echo "$FAIL TESTS FAILED"
  exit 1
fi
```

When reporting results:
- call out any case where browser-like traffic got `200` without proof
- call out any case where search crawlers were blocked
- call out any case where a blocked or challenged request still appeared to receive the protected content body
