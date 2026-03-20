# Bot Detection Test Prompt

Copy the prompt below into any AI coding agent (Claude Code, Codex, Cursor, etc.) and let it run.

---

## Prompt

Read the test script at `xenarch-plugins/tests/bot-detection-test-prompt.md` in this repo. Extract the bash script from the code block, save it to a temp file, make it executable, and run it. Report the full output and highlight any failures.

If you can't find that file, the test script is included below. Run it directly.

You are testing a bot detection system on `https://gate.xenarch.dev/sample-page/`. Run every curl command below and report the results in a table with columns: UA, Expected, Got, Pass/Fail.

**Rules:**
- Every bot/crawler/scraper UA should get HTTP **402**
- Every search engine crawler should get HTTP **200**
- Real browser with full headers should get HTTP **200**
- Empty UA should get HTTP **402**
- Spoofed browser (Chrome UA but no browser headers) should get HTTP **402**

Run ALL of these tests. Do not skip any. Report any failures.

```bash
#!/bin/bash
# Bot Detection Full Test Suite
# Target: https://gate.xenarch.dev/sample-page/
# Expected: 402 for bots, 200 for search crawlers and real browsers

URL="https://gate.xenarch.dev/sample-page/"
PASS=0
FAIL=0

test_expect() {
  local expected=$1
  local label=$2
  local ua=$3
  shift 3
  local extra_headers=("$@")

  local cmd=(curl -s -o /dev/null -w "%{http_code}" -H "User-Agent: $ua")
  for h in "${extra_headers[@]}"; do
    cmd+=(-H "$h")
  done
  cmd+=("$URL")

  local code=$("${cmd[@]}")
  if [ "$code" = "$expected" ]; then
    echo "PASS $code  $label"
    PASS=$((PASS+1))
  else
    echo "FAIL $code (expected $expected)  $label"
    FAIL=$((FAIL+1))
  fi
}

echo "========================================"
echo " BOT DETECTION TEST SUITE"
echo " Target: $URL"
echo " $(date -u)"
echo "========================================"

# ── OpenAI (expect 402) ──
echo ""
echo "── OpenAI ──"
test_expect 402 "GPTBot" "GPTBot/1.0"
test_expect 402 "OAI-SearchBot" "OAI-SearchBot/1.0"
test_expect 402 "ChatGPT-User" "ChatGPT-User/1.0"
test_expect 402 "ChatGPT Agent" "ChatGPT Agent/1.0"
test_expect 402 "OpenAI-Operator" "OpenAI-Operator/1.0"

# ── Anthropic (expect 402) ──
echo ""
echo "── Anthropic ──"
test_expect 402 "ClaudeBot" "ClaudeBot/1.0"
test_expect 402 "Claude-User" "Claude-User/1.0"
test_expect 402 "Claude-SearchBot" "Claude-SearchBot/1.0"
test_expect 402 "Claude-Web" "Claude-Web/1.0"
test_expect 402 "anthropic-ai" "anthropic-ai/1.0"

# ── Google AI (expect 402) ──
echo ""
echo "── Google AI ──"
test_expect 402 "Google-Extended" "Google-Extended"
test_expect 402 "Google-CloudVertexBot" "Google-CloudVertexBot/1.0"
test_expect 402 "Google-Firebase" "Google-Firebase/1.0"
test_expect 402 "Google-NotebookLM" "Google-NotebookLM/1.0"
test_expect 402 "GoogleAgent-Mariner" "GoogleAgent-Mariner/1.0"
test_expect 402 "GoogleOther" "GoogleOther"
test_expect 402 "GoogleOther-Image" "GoogleOther-Image/1.0"
test_expect 402 "GoogleOther-Video" "GoogleOther-Video/1.0"
test_expect 402 "Gemini-Deep-Research" "Gemini-Deep-Research/1.0"
test_expect 402 "CloudVertexBot" "CloudVertexBot/1.0"
test_expect 402 "NotebookLM" "NotebookLM/1.0"

# ── Perplexity (expect 402) ──
echo ""
echo "── Perplexity ──"
test_expect 402 "PerplexityBot" "PerplexityBot/1.0"
test_expect 402 "Perplexity-User" "Perplexity-User/1.0"

# ── Meta (expect 402) ──
echo ""
echo "── Meta ──"
test_expect 402 "Meta-ExternalAgent" "Meta-ExternalAgent/1.0"
test_expect 402 "meta-externalagent" "meta-externalagent/1.0"
test_expect 402 "Meta-ExternalFetcher" "Meta-ExternalFetcher/1.0"
test_expect 402 "meta-externalfetcher" "meta-externalfetcher/1.0"
test_expect 402 "meta-webindexer" "meta-webindexer/1.0"
test_expect 402 "FacebookBot" "FacebookBot/1.0"
test_expect 402 "facebookexternalhit" "facebookexternalhit/1.1"

# ── Amazon (expect 402) ──
echo ""
echo "── Amazon ──"
test_expect 402 "Amazonbot" "Amazonbot/1.0"
test_expect 402 "AmazonBuyForMe" "AmazonBuyForMe/1.0"
test_expect 402 "Amzn-SearchBot" "Amzn-SearchBot/1.0"
test_expect 402 "Amzn-User" "Amzn-User/1.0"
test_expect 402 "amazon-kendra" "amazon-kendra/1.0"
test_expect 402 "bedrockbot" "bedrockbot/1.0"
test_expect 402 "NovaAct" "NovaAct/1.0"

# ── Apple (expect 402 for Extended only) ──
echo ""
echo "── Apple ──"
test_expect 402 "Applebot-Extended" "Applebot-Extended/1.0"

# ── Microsoft / Azure (expect 402) ──
echo ""
echo "── Microsoft / Azure ──"
test_expect 402 "AzureAI-SearchBot" "AzureAI-SearchBot/1.0"

# ── ByteDance / TikTok (expect 402) ──
echo ""
echo "── ByteDance / TikTok ──"
test_expect 402 "Bytespider" "Bytespider/1.0"
test_expect 402 "TikTokSpider" "TikTokSpider/1.0"

# ── AI Coding Agents (expect 402) ──
echo ""
echo "── AI Coding Agents ──"
test_expect 402 "Devin" "Devin/1.0"
test_expect 402 "xenarch-cli" "xenarch-cli/1.0"

# ── AI Search / Assistants (expect 402) ──
echo ""
echo "── AI Search / Assistants ──"
test_expect 402 "Andibot" "Andibot/1.0"
test_expect 402 "Bravebot" "Bravebot/1.0"
test_expect 402 "DuckAssistBot" "DuckAssistBot/1.0"
test_expect 402 "iAskBot" "iAskBot/1.0"
test_expect 402 "iaskspider" "iaskspider/1.0"
test_expect 402 "kagi-fetcher" "kagi-fetcher/1.0"
test_expect 402 "LinerBot" "LinerBot/1.0"
test_expect 402 "Manus-User" "Manus-User/1.0"
test_expect 402 "MistralAI-User" "MistralAI-User/1.0"
test_expect 402 "PhindBot" "PhindBot/1.0"
test_expect 402 "TavilyBot" "TavilyBot/1.0"
test_expect 402 "YouBot" "YouBot/1.0"
test_expect 402 "WRTNBot" "WRTNBot/1.0"

# ── AI Data / Training Crawlers (expect 402) ──
echo ""
echo "── AI Data / Training Crawlers ──"
test_expect 402 "CCBot" "CCBot/2.0"
test_expect 402 "cohere-ai" "cohere-ai/1.0"
test_expect 402 "cohere-training-data-crawler" "cohere-training-data-crawler/1.0"
test_expect 402 "Diffbot" "Diffbot/1.0"
test_expect 402 "DeepSeekBot" "DeepSeekBot/1.0"
test_expect 402 "PetalBot" "PetalBot/1.0"
test_expect 402 "SemrushBot-OCOB" "SemrushBot-OCOB/1.0"
test_expect 402 "SemrushBot-SWA" "SemrushBot-SWA/1.0"
test_expect 402 "Ai2Bot" "Ai2Bot/1.0"
test_expect 402 "Ai2Bot-Dolma" "Ai2Bot-Dolma/1.0"
test_expect 402 "AI2Bot-DeepResearchEval" "AI2Bot-DeepResearchEval/1.0"
test_expect 402 "PanguBot" "PanguBot/1.0"
test_expect 402 "SBIntuitionsBot" "SBIntuitionsBot/1.0"
test_expect 402 "ChatGLM-Spider" "ChatGLM-Spider/1.0"
test_expect 402 "ExaBot" "ExaBot/1.0"

# ── Web Scraping / Data Tools (expect 402) ──
echo ""
echo "── Web Scraping / Data Tools ──"
test_expect 402 "Crawl4AI" "Crawl4AI/0.4.2"
test_expect 402 "FirecrawlAgent" "FirecrawlAgent/1.0"
test_expect 402 "FirecrawlAgent (Mozilla)" "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; FirecrawlAgent/1.0; +https://firecrawl.dev)"
test_expect 402 "ApifyBot" "ApifyBot/1.0"
test_expect 402 "ApifyWebsiteContentCrawler" "ApifyWebsiteContentCrawler/1.0"
test_expect 402 "Scrapy" "Scrapy/2.11.0 (+https://scrapy.org)"
test_expect 402 "img2dataset" "img2dataset/1.2.0"
test_expect 402 "Webzio-Extended" "Webzio-Extended/1.0"
test_expect 402 "ScrapingBee" "ScrapingBee"
test_expect 402 "ScrapingAnt" "ScrapingAnt/1.0"
test_expect 402 "ScrapingBot" "ScrapingBot/1.0"
test_expect 402 "ZenRows" "ZenRows/1.0"
test_expect 402 "Scrapfly" "Scrapfly/1.0"
test_expect 402 "ScrapeGraphAI" "ScrapeGraphAI/1.0"
test_expect 402 "WebScraper" "WebScraper/1.0"
test_expect 402 "ParseHub" "ParseHub/1.0"
test_expect 402 "Octoparse" "Octoparse/2.0"
test_expect 402 "BrowseAI" "BrowseAI/1.0"
test_expect 402 "DataForSEO" "DataForSEO/1.0"
test_expect 402 "Screaming Frog" "Screaming Frog SEO Spider/19.0"
test_expect 402 "Netpeak" "Netpeak Spider/1.0"

# ── SEO Crawlers (expect 402) ──
echo ""
echo "── SEO Crawlers ──"
test_expect 402 "Ahrefs" "Ahrefs/1.0"
test_expect 402 "AhrefsBot (Mozilla)" "Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)"
test_expect 402 "MJ12bot (Mozilla)" "Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)"
test_expect 402 "DotBot (Mozilla)" "Mozilla/5.0 (compatible; DotBot/1.2; +https://opensiteexplorer.org/dotbot)"
test_expect 402 "rogerbot" "rogerbot/1.2"
test_expect 402 "BLEXBot (Mozilla)" "Mozilla/5.0 (compatible; BLEXBot/1.0; +http://webmeup-crawler.com/)"
test_expect 402 "MegaIndex (Mozilla)" "Mozilla/5.0 (compatible; MegaIndex.ru/2.0)"
test_expect 402 "Nimbostratus" "Nimbostratus-Bot/v1.3.2"
test_expect 402 "Seekport (Mozilla)" "Mozilla/5.0 (compatible; Seekport Crawler)"

# ── AI Reader / RAG Tools (expect 402) ──
echo ""
echo "── AI Reader / RAG Tools ──"
test_expect 402 "JinaReader" "JinaReader/1.0"
test_expect 402 "Jina-AI" "Jina-AI/1.0"
test_expect 402 "LlamaIndex" "LlamaIndex/0.10"
test_expect 402 "LangChain" "LangChain/0.1"
test_expect 402 "Embedchain" "Embedchain/0.1"
test_expect 402 "Unstructured" "Unstructured/0.12"
test_expect 402 "Haystack" "Haystack/2.0"

# ── Social / Preview Bots (expect 402) ──
echo ""
echo "── Social / Preview Bots ──"
test_expect 402 "Twitterbot" "Twitterbot/1.0"
test_expect 402 "LinkedInBot" "LinkedInBot/1.0"
test_expect 402 "WhatsApp" "WhatsApp/2.23"
test_expect 402 "TelegramBot" "TelegramBot (like TwitterBot)"
test_expect 402 "Slackbot" "Slackbot 1.0"
test_expect 402 "Discordbot" "Discordbot/2.0"
test_expect 402 "SkypeUriPreview" "SkypeUriPreview/1.0"
test_expect 402 "Viber" "Viber/13.0"
test_expect 402 "Line" "Line/2.0"
test_expect 402 "Pinterest" "Pinterest/0.2 (+https://www.pinterest.com/bot.html)"
test_expect 402 "Pinterestbot" "Pinterestbot/1.0"
test_expect 402 "Snapchat" "Snapchat/1.0"

# ── Headless / Automation (expect 402) ──
echo ""
echo "── Headless / Automation ──"
test_expect 402 "HeadlessChrome" "HeadlessChrome/120.0.0.0"
test_expect 402 "PhantomJS" "PhantomJS/2.1.1"
test_expect 402 "Headless" "Headless/1.0"
test_expect 402 "Selenium" "Selenium/4.15.0"
test_expect 402 "Puppeteer" "Puppeteer/21.0"
test_expect 402 "Playwright" "Playwright/1.40"

# ── Other Known Bots (expect 402) ──
echo ""
echo "── Other Known Bots ──"
test_expect 402 "Timpibot" "Timpibot/1.0"
test_expect 402 "ImagesiftBot" "ImagesiftBot/1.0"
test_expect 402 "omgili" "omgili/1.0"
test_expect 402 "Kangaroo Bot" "Kangaroo Bot/1.0"
test_expect 402 "BuddyBot" "BuddyBot/1.0"
test_expect 402 "Cotoyogi" "Cotoyogi/1.0"
test_expect 402 "Brightbot" "Brightbot/1.0"
test_expect 402 "Panscient" "Panscient/1.0"
test_expect 402 "ShapBot" "ShapBot/1.0"
test_expect 402 "QuillBot" "QuillBot/1.0"
test_expect 402 "Poggio-Citations" "Poggio-Citations/1.0"
test_expect 402 "VelenPublicWebCrawler" "VelenPublicWebCrawler/1.0"
test_expect 402 "ZanistaBot" "ZanistaBot/1.0"

# ── HTTP Client Libraries (expect 402) ──
echo ""
echo "── HTTP Client Libraries ──"
test_expect 402 "python-requests" "python-requests/2.31.0"
test_expect 402 "python-httpx" "python-httpx/0.27.0"
test_expect 402 "httpx" "httpx/0.27.0"
test_expect 402 "axios" "axios/1.6.0"
test_expect 402 "Go-http-client" "Go-http-client/2.0"
test_expect 402 "node-fetch" "node-fetch/3.3.0"
test_expect 402 "undici" "undici/6.0.0"
test_expect 402 "got" "got/14.0.0"
test_expect 402 "curl" "curl/8.4.0"
test_expect 402 "wget" "wget/1.21"
test_expect 402 "Scrapy (lib)" "Scrapy/2.11.0"
test_expect 402 "libwww-perl" "libwww-perl/6.72"
test_expect 402 "Java" "Java/17.0.1"
test_expect 402 "aiohttp" "aiohttp/3.9.0"
test_expect 402 "http.rb" "http.rb/5.1.1"
test_expect 402 "okhttp" "okhttp/4.12.0"
test_expect 402 "HttpClient" "HttpClient/1.0"
test_expect 402 "Apache-HttpClient" "Apache-HttpClient/4.5.14"

# ── Empty UA (expect 402) ──
echo ""
echo "── Empty / Missing UA ──"
test_expect 402 "Empty UA" ""

# ── Default curl (expect 402) ──
echo ""
echo "── Default curl (no custom UA) ──"
code=$(curl -s -o /dev/null -w "%{http_code}" "$URL")
if [ "$code" = "402" ]; then
  echo "PASS $code  Default curl"
  PASS=$((PASS+1))
else
  echo "FAIL $code (expected 402)  Default curl"
  FAIL=$((FAIL+1))
fi

# ── Spoofed Browser (expect 402) ──
echo ""
echo "── Spoofed Browser (Chrome UA, no browser headers) ──"
test_expect 402 "Spoofed Chrome" \
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" \
  "Accept: */*"

# ══════════════════════════════════════
# THESE MUST GET 200 (not gated)
# ══════════════════════════════════════

echo ""
echo "========================================"
echo " SEARCH ENGINE CRAWLERS (expect 200)"
echo "========================================"

echo ""
echo "── Google Search ──"
test_expect 200 "Googlebot" "Googlebot/2.1 (+http://www.google.com/bot.html)"
test_expect 200 "Googlebot (Mozilla)" "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
test_expect 200 "Googlebot Mobile" "Mozilla/5.0 (Linux; Android 6.0.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 Googlebot/2.1"
test_expect 200 "Googlebot-Image" "Googlebot-Image/1.0"
test_expect 200 "Googlebot-Video" "Googlebot-Video/1.0"
test_expect 200 "AdsBot-Google" "AdsBot-Google (+http://www.google.com/adsbot.html)"
test_expect 200 "Mediapartners-Google" "Mediapartners-Google"

echo ""
echo "── Other Search Engines ──"
test_expect 200 "Bingbot" "Mozilla/5.0 (compatible; Bingbot/2.0; +http://www.bing.com/bingbot.htm)"
test_expect 200 "YandexBot" "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)"
test_expect 200 "DuckDuckBot" "Mozilla/5.0 (compatible; DuckDuckBot/1.1; https://duckduckgo.com/duckduckbot)"
test_expect 200 "Baiduspider" "Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)"
test_expect 200 "Applebot (search)" "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15 Applebot/0.1"

echo ""
echo "── Real Browser ──"
test_expect 200 "Chrome (full headers)" \
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" \
  "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  "Accept-Language: en-US,en;q=0.9" \
  "Accept-Encoding: gzip, deflate, br" \
  "Sec-Fetch-Mode: navigate" \
  "Sec-Fetch-Site: none" \
  "Sec-Fetch-Dest: document" \
  "Sec-Ch-Ua: \"Chromium\";v=\"120\", \"Google Chrome\";v=\"120\""

test_expect 200 "Firefox (full headers)" \
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0" \
  "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  "Accept-Language: en-US,en;q=0.5" \
  "Accept-Encoding: gzip, deflate, br" \
  "Sec-Fetch-Mode: navigate" \
  "Sec-Fetch-Site: none" \
  "Sec-Fetch-Dest: document"

test_expect 200 "Safari (full headers)" \
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15" \
  "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  "Accept-Language: en-US,en;q=0.9" \
  "Accept-Encoding: gzip, deflate, br" \
  "Sec-Fetch-Mode: navigate" \
  "Sec-Fetch-Dest: document" \
  "Sec-Fetch-Site: none"

# ══════════════════════════════════════
# SUMMARY
# ══════════════════════════════════════

echo ""
echo "========================================"
echo " RESULTS"
echo "========================================"
echo " Passed: $PASS"
echo " Failed: $FAIL"
echo " Total:  $((PASS + FAIL))"
echo ""
if [ $FAIL -eq 0 ]; then
  echo " ✅ ALL TESTS PASSED"
else
  echo " ❌ $FAIL TESTS FAILED — review output above"
fi
echo "========================================"
```

Run the entire script and report the results. If any test fails, highlight it clearly and explain what went wrong.
