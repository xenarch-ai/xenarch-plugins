<?php
/**
 * Xenarch bot detection — Joomla port.
 *
 * Classifies non-human traffic via:
 * - Known AI crawler User-Agent signatures
 * - Known HTTP client library signatures
 * - Browser-header consistency scoring
 * - Browser proof validation for suspicious browser-like requests
 *
 * Source: ai-robots-txt (https://github.com/ai-robots-txt/ai.robots.txt) + manual additions.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

class BotDetect
{
    /**
     * Known AI bot User-Agent signatures.
     */
    private static array $signatures = [
        // OpenAI
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ChatGPT Agent', 'OpenAI-Operator',
        // Anthropic
        'ClaudeBot', 'Claude-User', 'Claude-SearchBot', 'Claude-Web', 'anthropic-ai',
        // Google
        'Google-Extended', 'Google-CloudVertexBot', 'Google-Firebase', 'Google-NotebookLM',
        'GoogleAgent-Mariner', 'GoogleOther', 'GoogleOther-Image', 'GoogleOther-Video',
        'Gemini-Deep-Research', 'CloudVertexBot', 'NotebookLM',
        // Perplexity
        'PerplexityBot', 'Perplexity-User',
        // Meta
        'Meta-ExternalAgent', 'meta-externalagent', 'Meta-ExternalFetcher', 'meta-externalfetcher',
        'meta-webindexer', 'FacebookBot', 'facebookexternalhit',
        // Amazon
        'Amazonbot', 'AmazonBuyForMe', 'Amzn-SearchBot', 'Amzn-User', 'amazon-kendra', 'bedrockbot', 'NovaAct',
        // Apple
        'Applebot-Extended',
        // Microsoft / Azure
        'AzureAI-SearchBot',
        // ByteDance / TikTok
        'Bytespider', 'TikTokSpider',
        // AI Coding Agents
        'Devin', 'xenarch-cli',
        // AI Search / Assistants
        'Andibot', 'Bravebot', 'DuckAssistBot', 'iAskBot', 'iaskspider', 'kagi-fetcher',
        'LinerBot', 'Manus-User', 'MistralAI-User', 'PhindBot', 'TavilyBot', 'YouBot', 'WRTNBot',
        // AI Data / Training Crawlers
        'CCBot', 'cohere-ai', 'cohere-training-data-crawler', 'Diffbot', 'DeepSeekBot', 'PetalBot',
        'SemrushBot-OCOB', 'SemrushBot-SWA', 'Ai2Bot', 'Ai2Bot-Dolma', 'AI2Bot-DeepResearchEval',
        'PanguBot', 'SBIntuitionsBot', 'ChatGLM-Spider', 'ExaBot',
        // Web Scraping / Data Tools
        'Crawl4AI', 'FirecrawlAgent', 'ApifyBot', 'ApifyWebsiteContentCrawler', 'Scrapy', 'img2dataset',
        'Webzio-Extended', 'ScrapingBee', 'ScrapingAnt', 'ScrapingBot', 'ZenRows', 'Scrapfly',
        'ScrapeGraphAI', 'WebScraper', 'ParseHub', 'Octoparse', 'BrowseAI', 'DataForSEO',
        'Screaming Frog', 'Netpeak', 'Ahrefs', 'AhrefsBot', 'MJ12bot', 'DotBot', 'Rogerbot',
        'BLEXBot', 'MegaIndex', 'Nimbostratus', 'Seekport',
        // AI Reader / RAG Tools
        'JinaReader', 'Jina-AI', 'LlamaIndex', 'LangChain', 'Embedchain', 'Unstructured', 'Haystack',
        // Social / Preview Bots
        'Twitterbot', 'LinkedInBot', 'WhatsApp', 'TelegramBot', 'Slackbot', 'Discordbot',
        'SkypeUriPreview', 'Viber', 'Line/', 'Pinterest', 'Snapchat',
        // Other Known Bots
        'Timpibot', 'ImagesiftBot', 'omgili', 'Kangaroo Bot', 'BuddyBot', 'Cotoyogi', 'Brightbot',
        'Panscient', 'ShapBot', 'QuillBot', 'Poggio-Citations', 'VelenPublicWebCrawler', 'ZanistaBot',
        'HeadlessChrome', 'PhantomJS', 'Headless', 'Selenium', 'Puppeteer', 'Playwright',
    ];

    /**
     * HTTP client library signatures.
     */
    private static array $fetcherSignatures = [
        'python-requests', 'python-httpx', 'httpx/', 'axios/', 'Go-http-client', 'node-fetch',
        'undici', 'got/', 'curl/', 'wget/', 'Scrapy/', 'libwww-perl', 'Java/', 'aiohttp',
        'http.rb', 'okhttp', 'HttpClient', 'Apache-HttpClient',
    ];

    private const CAT_AI_SEARCH     = 'ai_search';
    private const CAT_AI_ASSISTANTS = 'ai_assistants';
    private const CAT_AI_AGENTS     = 'ai_agents';
    private const CAT_AI_TRAINING   = 'ai_training';
    private const CAT_SCRAPERS      = 'scrapers';
    private const CAT_GENERAL_AI    = 'general_ai';

    /**
     * Map each signature to its bot category.
     */
    private static array $signatureCategories = [
        // AI Search
        'OAI-SearchBot' => 'ai_search', 'Claude-SearchBot' => 'ai_search', 'PerplexityBot' => 'ai_search',
        'Amazonbot' => 'ai_search', 'Amzn-SearchBot' => 'ai_search', 'AzureAI-SearchBot' => 'ai_search',
        'Andibot' => 'ai_search', 'Bravebot' => 'ai_search', 'DuckAssistBot' => 'ai_search',
        'iAskBot' => 'ai_search', 'iaskspider' => 'ai_search', 'kagi-fetcher' => 'ai_search',
        'LinerBot' => 'ai_search', 'PhindBot' => 'ai_search', 'YouBot' => 'ai_search', 'WRTNBot' => 'ai_search',
        // AI Assistants
        'ChatGPT-User' => 'ai_assistants', 'Claude-User' => 'ai_assistants', 'Claude-Web' => 'ai_assistants',
        'Perplexity-User' => 'ai_assistants', 'Amzn-User' => 'ai_assistants', 'Manus-User' => 'ai_assistants',
        'MistralAI-User' => 'ai_assistants', 'Gemini-Deep-Research' => 'ai_assistants',
        'Google-NotebookLM' => 'ai_assistants', 'NotebookLM' => 'ai_assistants',
        // AI Agents
        'ChatGPT Agent' => 'ai_agents', 'OpenAI-Operator' => 'ai_agents', 'GoogleAgent-Mariner' => 'ai_agents',
        'Meta-ExternalAgent' => 'ai_agents', 'meta-externalagent' => 'ai_agents',
        'AmazonBuyForMe' => 'ai_agents', 'NovaAct' => 'ai_agents', 'Devin' => 'ai_agents', 'xenarch-cli' => 'ai_agents',
        // AI Training
        'Google-Extended' => 'ai_training', 'Applebot-Extended' => 'ai_training', 'CCBot' => 'ai_training',
        'cohere-training-data-crawler' => 'ai_training', 'Ai2Bot' => 'ai_training', 'Ai2Bot-Dolma' => 'ai_training',
        'AI2Bot-DeepResearchEval' => 'ai_training', 'PanguBot' => 'ai_training', 'SBIntuitionsBot' => 'ai_training',
        'ChatGLM-Spider' => 'ai_training', 'Bytespider' => 'ai_training', 'TikTokSpider' => 'ai_training',
        'img2dataset' => 'ai_training',
        // Scrapers
        'Crawl4AI' => 'scrapers', 'FirecrawlAgent' => 'scrapers', 'ApifyBot' => 'scrapers',
        'ApifyWebsiteContentCrawler' => 'scrapers', 'Scrapy' => 'scrapers', 'ScrapingBee' => 'scrapers',
        'ScrapingAnt' => 'scrapers', 'ScrapingBot' => 'scrapers', 'ZenRows' => 'scrapers',
        'Scrapfly' => 'scrapers', 'ScrapeGraphAI' => 'scrapers', 'WebScraper' => 'scrapers',
        'ParseHub' => 'scrapers', 'Octoparse' => 'scrapers', 'BrowseAI' => 'scrapers',
        'DataForSEO' => 'scrapers', 'Screaming Frog' => 'scrapers', 'Netpeak' => 'scrapers',
        'Ahrefs' => 'scrapers', 'AhrefsBot' => 'scrapers', 'MJ12bot' => 'scrapers', 'DotBot' => 'scrapers',
        'Rogerbot' => 'scrapers', 'BLEXBot' => 'scrapers', 'MegaIndex' => 'scrapers',
        'SemrushBot-OCOB' => 'scrapers', 'SemrushBot-SWA' => 'scrapers', 'Nimbostratus' => 'scrapers',
        'Seekport' => 'scrapers', 'Webzio-Extended' => 'scrapers', 'Diffbot' => 'scrapers',
        'HeadlessChrome' => 'scrapers', 'PhantomJS' => 'scrapers', 'Headless' => 'scrapers',
        'Selenium' => 'scrapers', 'Puppeteer' => 'scrapers', 'Playwright' => 'scrapers',
        'JinaReader' => 'scrapers', 'Jina-AI' => 'scrapers', 'LlamaIndex' => 'scrapers',
        'LangChain' => 'scrapers', 'Embedchain' => 'scrapers', 'Unstructured' => 'scrapers', 'Haystack' => 'scrapers',
        // General AI
        'GPTBot' => 'general_ai', 'ClaudeBot' => 'general_ai', 'anthropic-ai' => 'general_ai',
        'cohere-ai' => 'general_ai', 'DeepSeekBot' => 'general_ai', 'PetalBot' => 'general_ai',
        'Google-CloudVertexBot' => 'general_ai', 'CloudVertexBot' => 'general_ai',
        'Google-Firebase' => 'general_ai', 'GoogleOther' => 'general_ai',
        'GoogleOther-Image' => 'general_ai', 'GoogleOther-Video' => 'general_ai',
        'Meta-ExternalFetcher' => 'general_ai', 'meta-externalfetcher' => 'general_ai',
        'meta-webindexer' => 'general_ai', 'amazon-kendra' => 'general_ai', 'bedrockbot' => 'general_ai',
        'ExaBot' => 'general_ai', 'TavilyBot' => 'general_ai', 'Timpibot' => 'general_ai',
        'ImagesiftBot' => 'general_ai', 'omgili' => 'general_ai', 'Kangaroo Bot' => 'general_ai',
        'BuddyBot' => 'general_ai', 'Cotoyogi' => 'general_ai', 'Brightbot' => 'general_ai',
        'Panscient' => 'general_ai', 'ShapBot' => 'general_ai', 'QuillBot' => 'general_ai',
        'Poggio-Citations' => 'general_ai', 'VelenPublicWebCrawler' => 'general_ai', 'ZanistaBot' => 'general_ai',
    ];

    /**
     * Map each signature to its company/source name.
     */
    private static array $signatureCompanies = [
        'GPTBot' => 'OpenAI', 'OAI-SearchBot' => 'OpenAI', 'ChatGPT-User' => 'OpenAI', 'ChatGPT Agent' => 'OpenAI', 'OpenAI-Operator' => 'OpenAI',
        'ClaudeBot' => 'Anthropic', 'Claude-User' => 'Anthropic', 'Claude-SearchBot' => 'Anthropic', 'Claude-Web' => 'Anthropic', 'anthropic-ai' => 'Anthropic',
        'Google-Extended' => 'Google', 'Google-CloudVertexBot' => 'Google', 'Google-Firebase' => 'Google', 'Google-NotebookLM' => 'Google',
        'GoogleAgent-Mariner' => 'Google', 'GoogleOther' => 'Google', 'GoogleOther-Image' => 'Google', 'GoogleOther-Video' => 'Google',
        'Gemini-Deep-Research' => 'Google', 'CloudVertexBot' => 'Google', 'NotebookLM' => 'Google',
        'PerplexityBot' => 'Perplexity', 'Perplexity-User' => 'Perplexity',
        'Meta-ExternalAgent' => 'Meta', 'meta-externalagent' => 'Meta', 'Meta-ExternalFetcher' => 'Meta', 'meta-externalfetcher' => 'Meta', 'meta-webindexer' => 'Meta',
        'FacebookBot' => 'Meta', 'facebookexternalhit' => 'Meta',
        'Amazonbot' => 'Amazon', 'AmazonBuyForMe' => 'Amazon', 'Amzn-SearchBot' => 'Amazon', 'Amzn-User' => 'Amazon', 'amazon-kendra' => 'Amazon', 'bedrockbot' => 'Amazon', 'NovaAct' => 'Amazon',
        'Applebot-Extended' => 'Apple', 'AzureAI-SearchBot' => 'Microsoft',
        'Bytespider' => 'ByteDance', 'TikTokSpider' => 'ByteDance',
        'Devin' => 'Cognition', 'xenarch-cli' => 'Xenarch',
        'Andibot' => 'Andi', 'Bravebot' => 'Brave', 'DuckAssistBot' => 'DuckDuckGo',
        'iAskBot' => 'iAsk.AI', 'iaskspider' => 'iAsk.AI', 'kagi-fetcher' => 'Kagi', 'LinerBot' => 'Liner',
        'Manus-User' => 'Manus', 'MistralAI-User' => 'Mistral', 'PhindBot' => 'Phind',
        'TavilyBot' => 'Tavily', 'YouBot' => 'You.com', 'WRTNBot' => 'WRTN',
        'CCBot' => 'Common Crawl', 'cohere-ai' => 'Cohere', 'cohere-training-data-crawler' => 'Cohere',
        'Diffbot' => 'Diffbot', 'DeepSeekBot' => 'DeepSeek', 'PetalBot' => 'Huawei',
        'SemrushBot-OCOB' => 'Semrush', 'SemrushBot-SWA' => 'Semrush',
        'Ai2Bot' => 'Allen AI', 'Ai2Bot-Dolma' => 'Allen AI', 'AI2Bot-DeepResearchEval' => 'Allen AI',
        'PanguBot' => 'Huawei', 'SBIntuitionsBot' => 'SB Intuitions', 'ChatGLM-Spider' => 'Zhipu AI', 'ExaBot' => 'Exa',
        'Crawl4AI' => 'Crawl4AI', 'FirecrawlAgent' => 'Firecrawl', 'ApifyBot' => 'Apify', 'ApifyWebsiteContentCrawler' => 'Apify',
        'Scrapy' => 'Scrapy', 'img2dataset' => 'Open source', 'Webzio-Extended' => 'Webz.io',
        'ScrapingBee' => 'ScrapingBee', 'ScrapingAnt' => 'ScrapingAnt', 'ScrapingBot' => 'ScrapingBot',
        'ZenRows' => 'ZenRows', 'Scrapfly' => 'Scrapfly', 'ScrapeGraphAI' => 'ScrapeGraphAI',
        'WebScraper' => 'WebScraper', 'ParseHub' => 'ParseHub', 'Octoparse' => 'Octoparse',
        'BrowseAI' => 'BrowseAI', 'DataForSEO' => 'DataForSEO',
        'Screaming Frog' => 'Screaming Frog', 'Netpeak' => 'Netpeak',
        'Ahrefs' => 'Ahrefs', 'AhrefsBot' => 'Ahrefs', 'MJ12bot' => 'Majestic', 'DotBot' => 'Moz', 'Rogerbot' => 'Moz',
        'BLEXBot' => 'BLEXBot', 'MegaIndex' => 'MegaIndex', 'Nimbostratus' => 'Nimbostratus', 'Seekport' => 'Seekport',
        'JinaReader' => 'Jina AI', 'Jina-AI' => 'Jina AI', 'LlamaIndex' => 'LlamaIndex', 'LangChain' => 'LangChain',
        'Embedchain' => 'Embedchain', 'Unstructured' => 'Unstructured', 'Haystack' => 'Haystack',
        'Twitterbot' => 'X/Twitter', 'LinkedInBot' => 'LinkedIn', 'WhatsApp' => 'WhatsApp', 'TelegramBot' => 'Telegram',
        'Slackbot' => 'Slack', 'Discordbot' => 'Discord', 'SkypeUriPreview' => 'Skype', 'Viber' => 'Viber',
        'Line/' => 'LINE', 'Pinterest' => 'Pinterest', 'Snapchat' => 'Snapchat',
        'HeadlessChrome' => 'Google', 'PhantomJS' => 'PhantomJS', 'Headless' => 'Headless', 'Selenium' => 'Selenium', 'Puppeteer' => 'Google', 'Playwright' => 'Microsoft',
        'Timpibot' => 'Timpi', 'ImagesiftBot' => 'Imagesift', 'omgili' => 'Omgili', 'Kangaroo Bot' => 'Kangaroo',
        'BuddyBot' => 'Buddy', 'Cotoyogi' => 'Cotoyogi', 'Brightbot' => 'Bright Data', 'Panscient' => 'Panscient',
        'ShapBot' => 'Shap', 'QuillBot' => 'QuillBot', 'Poggio-Citations' => 'Poggio',
        'VelenPublicWebCrawler' => 'Velen', 'ZanistaBot' => 'Zanista',
    ];

    private const HEADER_SCORE_THRESHOLD = 6;

    /**
     * Full traffic classification with header scoring and browser proof.
     */
    public static function detectFull(string $userAgent, array $headers = [], array $context = []): array
    {
        // 1. Empty UA → instant block.
        if (empty($userAgent)) {
            return self::buildResult('block', 'known_fetcher', 'empty_ua', 0, ['empty_ua']);
        }

        // 2. Signature match.
        foreach (self::$signatures as $signature) {
            if (stripos($userAgent, $signature) !== false) {
                $trafficClass = self::trafficClassForSignature($signature);
                if ($trafficClass === 'social_preview_fetcher') {
                    return self::buildResult('allow', 'social_preview_fetcher', 'social_allowlist', 0, [$signature]);
                }
                return self::buildResult('block', $trafficClass, 'ua_match', 0, [$signature]);
            }
        }

        // 3. Trusted search crawler → always allow.
        if (self::isSearchCrawler($userAgent)) {
            return self::buildResult('allow', 'trusted_search_crawler', 'search_allowlist', 0, []);
        }

        // 4. HTTP client library match → instant block.
        foreach (self::$fetcherSignatures as $signature) {
            if (stripos($userAgent, $signature) !== false) {
                return self::buildResult('block', 'known_fetcher', 'fetcher_ua', 0, [$signature]);
            }
        }

        // 5. Browser proof short-circuits.
        if (!empty($context['browser_proof_valid'])) {
            return self::buildResult('allow', 'trusted_browser', 'browser_proof', 0, ['browser_proof']);
        }

        // 6. Browser-claiming UA → classify.
        if (stripos($userAgent, 'Mozilla/') !== false) {
            $score = 0;
            $signals = [];

            $accept = $headers['Accept'] ?? '';
            if (empty($accept) || trim($accept) === '*/*') {
                $score += 2;
                $signals[] = 'accept';
            }

            if (empty($headers['Accept-Language'] ?? '')) {
                $score += 2;
                $signals[] = 'accept-language';
            }

            if (empty($headers['Sec-Fetch-Mode'] ?? '')) {
                $score += 2;
                $signals[] = 'sec-fetch-mode';
            }

            if (empty($headers['Sec-Fetch-Dest'] ?? '')) {
                $score += 1;
                $signals[] = 'sec-fetch-dest';
            }

            if (empty($headers['Sec-Fetch-Site'] ?? '')) {
                $score += 1;
                $signals[] = 'sec-fetch-site';
            }

            if (empty($headers['Sec-Ch-Ua'] ?? '') && (stripos($userAgent, 'Chrome') !== false || stripos($userAgent, 'Edg') !== false)) {
                $score += 1;
                $signals[] = 'sec-ch-ua';
            }

            $acceptEnc = $headers['Accept-Encoding'] ?? '';
            if (stripos($acceptEnc, 'br') === false) {
                $score += 1;
                $signals[] = 'accept-encoding';
            }

            if (empty($headers['Sec-Fetch-User'] ?? '')) {
                $score += 1;
                $signals[] = 'sec-fetch-user';
            }

            if (empty($headers['Upgrade-Insecure-Requests'] ?? '')) {
                $score += 1;
                $signals[] = 'upgrade-insecure-requests';
            }

            if ($score >= self::HEADER_SCORE_THRESHOLD) {
                return self::buildResult('block', 'browser_like_suspicious', 'header_score:' . $score, $score, $signals);
            }

            return self::buildResult('challenge', 'browser_like_suspicious', 'browser_headers', $score, $signals);
        }

        // 7. Unknown non-browser traffic.
        return self::buildResult('block', 'unknown_non_browser', 'unknown_non_browser', 0, ['unknown_non_browser']);
    }

    public static function getSignatures(): array
    {
        return self::$signatures;
    }

    public static function getFetcherSignatures(): array
    {
        return self::$fetcherSignatures;
    }

    public static function getCategoryForSignature(string $signature): ?string
    {
        return self::$signatureCategories[$signature] ?? null;
    }

    public static function getCompanyForSignature(string $signature): string
    {
        return self::$signatureCompanies[$signature] ?? '';
    }

    public static function autoCategorize(string $userAgent): string
    {
        if (stripos($userAgent, '-User') !== false) return self::CAT_AI_ASSISTANTS;
        if (stripos($userAgent, 'SearchBot') !== false) return self::CAT_AI_SEARCH;
        if (stripos($userAgent, 'Agent') !== false || stripos($userAgent, 'Operator') !== false) return self::CAT_AI_AGENTS;
        if (stripos($userAgent, '-Extended') !== false || stripos($userAgent, 'training') !== false || stripos($userAgent, 'dataset') !== false) return self::CAT_AI_TRAINING;
        if (stripos($userAgent, 'Spider') !== false || stripos($userAgent, 'Scraper') !== false || stripos($userAgent, 'Scraping') !== false || stripos($userAgent, 'Crawl') !== false) return self::CAT_SCRAPERS;
        return self::CAT_GENERAL_AI;
    }

    public static function getCategoryKeys(): array
    {
        return [self::CAT_AI_SEARCH, self::CAT_AI_ASSISTANTS, self::CAT_AI_AGENTS, self::CAT_AI_TRAINING, self::CAT_SCRAPERS, self::CAT_GENERAL_AI];
    }

    public static function getAllSignatureMetadata(): array
    {
        $result = [];
        foreach (self::$signatureCategories as $sig => $cat) {
            $result[] = [
                'name'     => $sig,
                'category' => $cat,
                'company'  => self::$signatureCompanies[$sig] ?? '',
            ];
        }
        return $result;
    }

    public static function getSignaturesByCategory(): array
    {
        $grouped = [];
        foreach (self::$signatureCategories as $sig => $cat) {
            $grouped[$cat][] = $sig;
        }
        return $grouped;
    }

    private static function buildResult(string $decision, string $trafficClass, string $method, int $score, array $signals): array
    {
        $category = null;
        if ($method === 'ua_match' && !empty($signals[0])) {
            $category = self::getCategoryForSignature($signals[0]);
        }

        return [
            'decision'      => $decision,
            'traffic_class' => $trafficClass,
            'method'        => $method,
            'score'         => $score,
            'signals'       => $signals,
            'signal'        => implode(',', $signals),
            'confidence'    => $decision === 'block' ? 'high' : ($decision === 'challenge' ? 'medium' : 'high'),
            'is_bot'        => $decision !== 'allow',
            'category'      => $category,
        ];
    }

    private static function trafficClassForSignature(string $signature): string
    {
        $socialPreview = [
            'FacebookBot', 'facebookexternalhit', 'Twitterbot', 'LinkedInBot',
            'WhatsApp', 'TelegramBot', 'Slackbot', 'Discordbot',
            'SkypeUriPreview', 'Viber', 'Line/', 'Pinterest', 'Snapchat',
        ];

        if (in_array($signature, $socialPreview, true)) {
            return 'social_preview_fetcher';
        }

        $category = self::getCategoryForSignature($signature);
        return $category ?: 'known_ai_crawler';
    }

    private static function isSearchCrawler(string $userAgent): bool
    {
        $patterns = [
            '/\bGooglebot(?:-[A-Za-z]+)?(?:\/|\b)/i',
            '/\bAdsBot-Google(?:\/|\b)/i',
            '/\bMediapartners-Google(?:\/|\b)/i',
            '/\bBingbot(?:\/|\b)/i',
            '/\bSlurp(?:\/|\b)/i',
            '/\bDuckDuckBot(?:\/|\b)/i',
            '/\bBaiduspider(?:\/|\b)/i',
            '/\bYandexBot(?:\/|\b)/i',
            '/\bSogou(?:\/|\b)/i',
            '/\bApplebot(?:\/|\b)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                return true;
            }
        }

        return false;
    }
}
