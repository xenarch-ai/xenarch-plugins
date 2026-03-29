<?php
/**
 * Xenarch bot detection.
 *
 * Classifies non-human traffic via:
 * - Known AI crawler User-Agent signatures
 * - Known HTTP client library signatures
 * - Browser-header consistency scoring
 * - Browser proof validation for suspicious browser-like requests
 *
 * Source: ai-robots-txt (https://github.com/ai-robots-txt/ai.robots.txt) + manual additions.
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Bot_Detect
 */
class Xenarch_Bot_Detect {

	/**
	 * Known AI bot User-Agent signatures.
	 * Source: ai-robots-txt + manual additions for AI coding agents.
	 *
	 * @var string[]
	 */
	private static $signatures = array(
		// OpenAI
		'GPTBot',
		'OAI-SearchBot',
		'ChatGPT-User',
		'ChatGPT Agent',
		'OpenAI-Operator',

		// Anthropic
		'ClaudeBot',
		'Claude-User',
		'Claude-SearchBot',
		'Claude-Web',
		'anthropic-ai',

		// Google
		'Google-Extended',
		'Google-CloudVertexBot',
		'Google-Firebase',
		'Google-NotebookLM',
		'GoogleAgent-Mariner',
		'GoogleOther',
		'GoogleOther-Image',
		'GoogleOther-Video',
		'Gemini-Deep-Research',
		'CloudVertexBot',
		'NotebookLM',

		// Perplexity
		'PerplexityBot',
		'Perplexity-User',

		// Meta
		'Meta-ExternalAgent',
		'meta-externalagent',
		'Meta-ExternalFetcher',
		'meta-externalfetcher',
		'meta-webindexer',
		'FacebookBot',
		'facebookexternalhit',

		// Amazon
		'Amazonbot',
		'AmazonBuyForMe',
		'Amzn-SearchBot',
		'Amzn-User',
		'amazon-kendra',
		'bedrockbot',
		'NovaAct',

		// Apple (only Extended — regular Applebot is a search crawler)
		'Applebot-Extended',

		// Microsoft / Azure
		'AzureAI-SearchBot',

		// ByteDance / TikTok
		'Bytespider',
		'TikTokSpider',

		// AI Coding Agents
		'Devin',
		'xenarch-cli',

		// AI Search / Assistants
		'Andibot',
		'Bravebot',
		'DuckAssistBot',
		'iAskBot',
		'iaskspider',
		'kagi-fetcher',
		'LinerBot',
		'Manus-User',
		'MistralAI-User',
		'PhindBot',
		'TavilyBot',
		'YouBot',
		'WRTNBot',

		// AI Data / Training Crawlers
		'CCBot',
		'cohere-ai',
		'cohere-training-data-crawler',
		'Diffbot',
		'DeepSeekBot',
		'PetalBot',
		'SemrushBot-OCOB',
		'SemrushBot-SWA',
		'Ai2Bot',
		'Ai2Bot-Dolma',
		'AI2Bot-DeepResearchEval',
		'PanguBot',
		'SBIntuitionsBot',
		'ChatGLM-Spider',
		'ExaBot',

		// Web Scraping / Data Tools
		'Crawl4AI',
		'FirecrawlAgent',
		'ApifyBot',
		'ApifyWebsiteContentCrawler',
		'Scrapy',
		'img2dataset',
		'Webzio-Extended',
		'ScrapingBee',
		'ScrapingAnt',
		'ScrapingBot',
		'ZenRows',
		'Scrapfly',
		'ScrapeGraphAI',
		'WebScraper',
		'ParseHub',
		'Octoparse',
		'BrowseAI',
		'DataForSEO',
		'Screaming Frog',
		'Netpeak',
		'Ahrefs',
		'AhrefsBot',
		'MJ12bot',
		'DotBot',
		'Rogerbot',
		'BLEXBot',
		'MegaIndex',
		'Nimbostratus',
		'Seekport',

		// AI Reader / RAG Tools
		'JinaReader',
		'Jina-AI',
		'LlamaIndex',
		'LangChain',
		'Embedchain',
		'Unstructured',
		'Haystack',

		// Social / Preview Bots
		'Twitterbot',
		'LinkedInBot',
		'WhatsApp',
		'TelegramBot',
		'Slackbot',
		'Discordbot',
		'SkypeUriPreview',
		'Viber',
		'Line/',
		'Pinterest',
		'Snapchat',

		// Other Known Bots
		'Timpibot',
		'ImagesiftBot',
		'omgili',
		'Kangaroo Bot',
		'BuddyBot',
		'Cotoyogi',
		'Brightbot',
		'Panscient',
		'ShapBot',
		'QuillBot',
		'Poggio-Citations',
		'VelenPublicWebCrawler',
		'ZanistaBot',
		'HeadlessChrome',
		'PhantomJS',
		'Headless',
		'Selenium',
		'Puppeteer',
		'Playwright',
	);

	/**
	 * HTTP client library signatures.
	 * No human browses with these — definitive bot indicators.
	 *
	 * @var string[]
	 */
	private static $fetcher_signatures = array(
		'python-requests',
		'python-httpx',
		'httpx/',
		'axios/',
		'Go-http-client',
		'node-fetch',
		'undici',
		'got/',
		'curl/',
		'wget/',
		'Scrapy/',
		'libwww-perl',
		'Java/',
		'aiohttp',
		'http.rb',
		'okhttp',
		'HttpClient',
		'Apache-HttpClient',
	);

	/**
	 * Bot category constants.
	 */
	const CAT_AI_SEARCH    = 'ai_search';
	const CAT_AI_ASSISTANTS = 'ai_assistants';
	const CAT_AI_AGENTS    = 'ai_agents';
	const CAT_AI_TRAINING  = 'ai_training';
	const CAT_SCRAPERS     = 'scrapers';
	const CAT_GENERAL_AI   = 'general_ai';

	/**
	 * Map each signature to its bot category.
	 * Source: Information/design/bot-classification.md
	 *
	 * @var array<string, string>
	 */
	private static $signature_categories = array(
		// AI Search (default: allow).
		'OAI-SearchBot'     => 'ai_search',
		'Claude-SearchBot'  => 'ai_search',
		'PerplexityBot'     => 'ai_search',
		'Amazonbot'         => 'ai_search',
		'Amzn-SearchBot'    => 'ai_search',
		'AzureAI-SearchBot' => 'ai_search',
		'Andibot'           => 'ai_search',
		'Bravebot'          => 'ai_search',
		'DuckAssistBot'     => 'ai_search',
		'iAskBot'           => 'ai_search',
		'iaskspider'        => 'ai_search',
		'kagi-fetcher'      => 'ai_search',
		'LinerBot'          => 'ai_search',
		'PhindBot'          => 'ai_search',
		'YouBot'            => 'ai_search',
		'WRTNBot'           => 'ai_search',

		// AI Assistants (default: charge).
		'ChatGPT-User'      => 'ai_assistants',
		'Claude-User'       => 'ai_assistants',
		'Claude-Web'        => 'ai_assistants',
		'Perplexity-User'   => 'ai_assistants',
		'Amzn-User'         => 'ai_assistants',
		'Manus-User'        => 'ai_assistants',
		'MistralAI-User'    => 'ai_assistants',
		'Gemini-Deep-Research' => 'ai_assistants',
		'Google-NotebookLM' => 'ai_assistants',
		'NotebookLM'        => 'ai_assistants',

		// AI Agents (default: charge).
		'ChatGPT Agent'       => 'ai_agents',
		'OpenAI-Operator'     => 'ai_agents',
		'GoogleAgent-Mariner' => 'ai_agents',
		'Meta-ExternalAgent'  => 'ai_agents',
		'meta-externalagent'  => 'ai_agents',
		'AmazonBuyForMe'      => 'ai_agents',
		'NovaAct'             => 'ai_agents',
		'Devin'               => 'ai_agents',
		'xenarch-cli'         => 'ai_agents',

		// AI Training (default: charge).
		'Google-Extended'             => 'ai_training',
		'Applebot-Extended'           => 'ai_training',
		'CCBot'                       => 'ai_training',
		'cohere-training-data-crawler' => 'ai_training',
		'Ai2Bot'                      => 'ai_training',
		'Ai2Bot-Dolma'                => 'ai_training',
		'AI2Bot-DeepResearchEval'     => 'ai_training',
		'PanguBot'                    => 'ai_training',
		'SBIntuitionsBot'             => 'ai_training',
		'ChatGLM-Spider'              => 'ai_training',
		'Bytespider'                  => 'ai_training',
		'TikTokSpider'                => 'ai_training',
		'img2dataset'                 => 'ai_training',

		// Scrapers (default: charge).
		'Crawl4AI'                  => 'scrapers',
		'FirecrawlAgent'            => 'scrapers',
		'ApifyBot'                  => 'scrapers',
		'ApifyWebsiteContentCrawler' => 'scrapers',
		'Scrapy'                    => 'scrapers',
		'ScrapingBee'               => 'scrapers',
		'ScrapingAnt'               => 'scrapers',
		'ScrapingBot'               => 'scrapers',
		'ZenRows'                   => 'scrapers',
		'Scrapfly'                  => 'scrapers',
		'ScrapeGraphAI'             => 'scrapers',
		'WebScraper'                => 'scrapers',
		'ParseHub'                  => 'scrapers',
		'Octoparse'                 => 'scrapers',
		'BrowseAI'                  => 'scrapers',
		'DataForSEO'                => 'scrapers',
		'Screaming Frog'            => 'scrapers',
		'Netpeak'                   => 'scrapers',
		'Ahrefs'                    => 'scrapers',
		'AhrefsBot'                 => 'scrapers',
		'MJ12bot'                   => 'scrapers',
		'DotBot'                    => 'scrapers',
		'Rogerbot'                  => 'scrapers',
		'BLEXBot'                   => 'scrapers',
		'MegaIndex'                 => 'scrapers',
		'SemrushBot-OCOB'           => 'scrapers',
		'SemrushBot-SWA'            => 'scrapers',
		'Nimbostratus'              => 'scrapers',
		'Seekport'                  => 'scrapers',
		'Webzio-Extended'           => 'scrapers',
		'Diffbot'                   => 'scrapers',
		'HeadlessChrome'            => 'scrapers',
		'PhantomJS'                 => 'scrapers',
		'Headless'                  => 'scrapers',
		'Selenium'                  => 'scrapers',
		'Puppeteer'                 => 'scrapers',
		'Playwright'                => 'scrapers',
		'JinaReader'                => 'scrapers',
		'Jina-AI'                   => 'scrapers',
		'LlamaIndex'                => 'scrapers',
		'LangChain'                 => 'scrapers',
		'Embedchain'                => 'scrapers',
		'Unstructured'              => 'scrapers',
		'Haystack'                  => 'scrapers',

		// General AI (default: charge) — ambiguous intent.
		'GPTBot'               => 'general_ai',
		'ClaudeBot'            => 'general_ai',
		'anthropic-ai'         => 'general_ai',
		'cohere-ai'            => 'general_ai',
		'DeepSeekBot'          => 'general_ai',
		'PetalBot'             => 'general_ai',
		'Google-CloudVertexBot' => 'general_ai',
		'CloudVertexBot'       => 'general_ai',
		'Google-Firebase'      => 'general_ai',
		'GoogleOther'          => 'general_ai',
		'GoogleOther-Image'    => 'general_ai',
		'GoogleOther-Video'    => 'general_ai',
		'Meta-ExternalFetcher' => 'general_ai',
		'meta-externalfetcher' => 'general_ai',
		'meta-webindexer'      => 'general_ai',
		'amazon-kendra'        => 'general_ai',
		'bedrockbot'           => 'general_ai',
		'ExaBot'               => 'general_ai',
		'TavilyBot'            => 'general_ai',
		'Timpibot'             => 'general_ai',
		'ImagesiftBot'         => 'general_ai',
		'omgili'               => 'general_ai',
		'Kangaroo Bot'         => 'general_ai',
		'BuddyBot'             => 'general_ai',
		'Cotoyogi'             => 'general_ai',
		'Brightbot'            => 'general_ai',
		'Panscient'            => 'general_ai',
		'ShapBot'              => 'general_ai',
		'QuillBot'             => 'general_ai',
		'Poggio-Citations'     => 'general_ai',
		'VelenPublicWebCrawler' => 'general_ai',
		'ZanistaBot'           => 'general_ai',
	);

	/**
	 * Map each signature to its company/source name.
	 *
	 * @var array<string, string>
	 */
	private static $signature_companies = array(
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
	);

	/**
	 * Header scoring threshold for a hard block.
	 */
	const HEADER_SCORE_THRESHOLD = 6;

	/**
	 * Detect if a User-Agent string matches a known AI bot.
	 * Backwards-compatible — returns matched signature or false.
	 *
	 * @param string $user_agent The User-Agent header value.
	 * @return string|false Matched signature string, or false if not a bot.
	 */
	public static function detect( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return 'empty_ua';
		}

		foreach ( self::$signatures as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				return $signature;
			}
		}

		foreach ( self::$fetcher_signatures as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				return $signature;
			}
		}

		return false;
	}

	/**
	 * Full traffic classification with header scoring and browser proof.
	 *
	 * @param string $user_agent The User-Agent header value.
	 * @param array  $headers    Associative array of HTTP headers.
	 * @param array  $context    Additional request context.
	 * @return array Detection result with backwards-compatible fields.
	 */
	public static function detect_full( $user_agent, $headers = array(), $context = array() ) {
		// 1. Empty UA → instant block.
		if ( empty( $user_agent ) ) {
			return self::build_result( 'block', 'known_fetcher', 'empty_ua', 0, array( 'empty_ua' ) );
		}

		// 2. Signature match — block AI crawlers, allow social preview bots.
		foreach ( self::$signatures as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				$traffic_class = self::traffic_class_for_signature( $signature );
				// Social preview bots always pass through free (not configurable).
				if ( 'social_preview_fetcher' === $traffic_class ) {
					return self::build_result( 'allow', 'social_preview_fetcher', 'social_allowlist', 0, array( $signature ) );
				}
				return self::build_result( 'block', $traffic_class, 'ua_match', 0, array( $signature ) );
			}
		}

		// 3. Trusted search crawler → always allow.
		if ( self::is_search_crawler( $user_agent ) ) {
			return self::build_result( 'allow', 'trusted_search_crawler', 'search_allowlist', 0, array() );
		}

		// 4. HTTP client library match → instant block.
		foreach ( self::$fetcher_signatures as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				return self::build_result( 'block', 'known_fetcher', 'fetcher_ua', 0, array( $signature ) );
			}
		}

		// 5. Browser proof short-circuits suspicious browser traffic into allow.
		if ( ! empty( $context['browser_proof_valid'] ) ) {
			return self::build_result( 'allow', 'trusted_browser', 'browser_proof', 0, array( 'browser_proof' ) );
		}

		// 6. Browser-claiming UA → classify as block or challenge.
		if ( false !== stripos( $user_agent, 'Mozilla/' ) ) {
			$score   = 0;
			$signals = array();

			$accept = isset( $headers['Accept'] ) ? $headers['Accept'] : '';
			if ( empty( $accept ) || '*/*' === trim( $accept ) ) {
				$score += 2;
				$signals[] = 'accept';
			}

			$accept_lang = isset( $headers['Accept-Language'] ) ? $headers['Accept-Language'] : '';
			if ( empty( $accept_lang ) ) {
				$score += 2;
				$signals[] = 'accept-language';
			}

			if ( empty( $headers['Sec-Fetch-Mode'] ) ) {
				$score += 2;
				$signals[] = 'sec-fetch-mode';
			}

			if ( empty( $headers['Sec-Fetch-Dest'] ) ) {
				$score += 1;
				$signals[] = 'sec-fetch-dest';
			}

			if ( empty( $headers['Sec-Fetch-Site'] ) ) {
				$score += 1;
				$signals[] = 'sec-fetch-site';
			}

			if ( empty( $headers['Sec-Ch-Ua'] ) && ( false !== stripos( $user_agent, 'Chrome' ) || false !== stripos( $user_agent, 'Edg' ) ) ) {
				$score += 1;
				$signals[] = 'sec-ch-ua';
			}

			$accept_enc = isset( $headers['Accept-Encoding'] ) ? $headers['Accept-Encoding'] : '';
			if ( false === stripos( $accept_enc, 'br' ) ) {
				$score += 1;
				$signals[] = 'accept-encoding';
			}

			if ( empty( $headers['Sec-Fetch-User'] ) ) {
				$score += 1;
				$signals[] = 'sec-fetch-user';
			}

			if ( empty( $headers['Upgrade-Insecure-Requests'] ) ) {
				$score += 1;
				$signals[] = 'upgrade-insecure-requests';
			}

			if ( $score >= self::HEADER_SCORE_THRESHOLD ) {
				return self::build_result( 'block', 'browser_like_suspicious', 'header_score:' . $score, $score, $signals );
			}

			return self::build_result( 'challenge', 'browser_like_suspicious', 'browser_headers', $score, $signals );
		}

		// 7. Unknown non-browser traffic should not be trusted as human.
		return self::build_result( 'block', 'unknown_non_browser', 'unknown_non_browser', 0, array( 'unknown_non_browser' ) );
	}

	/**
	 * Get all bot signatures.
	 *
	 * @return string[]
	 */
	public static function get_signatures() {
		return self::$signatures;
	}

	/**
	 * Get all fetcher signatures.
	 *
	 * @return string[]
	 */
	public static function get_fetcher_signatures() {
		return self::$fetcher_signatures;
	}

	/**
	 * Build a result array with backwards-compatible keys.
	 *
	 * @param string   $decision      allow|block|challenge.
	 * @param string   $traffic_class Traffic class label.
	 * @param string   $method        Detection method.
	 * @param int      $score         Classification score.
	 * @param string[] $signals       Detection signals.
	 * @return array
	 */
	private static function build_result( $decision, $traffic_class, $method, $score, $signals ) {
		// Resolve category from first signal (matched signature) when available.
		$category = null;
		if ( 'ua_match' === $method && ! empty( $signals[0] ) ) {
			$category = self::get_category_for_signature( $signals[0] );
		}

		return array(
			'decision'      => $decision,
			'traffic_class' => $traffic_class,
			'method'        => $method,
			'score'         => $score,
			'signals'       => $signals,
			'signal'        => implode( ',', $signals ),
			'confidence'    => 'block' === $decision ? 'high' : ( 'challenge' === $decision ? 'medium' : 'high' ),
			'is_bot'        => 'allow' !== $decision,
			'category'      => $category,
		);
	}

	/**
	 * Infer traffic class from a matched signature.
	 *
	 * @param string $signature Matched user-agent signature.
	 * @return string
	 */
	private static function traffic_class_for_signature( $signature ) {
		static $social_preview = array(
			'FacebookBot', 'facebookexternalhit', 'Twitterbot', 'LinkedInBot',
			'WhatsApp', 'TelegramBot', 'Slackbot', 'Discordbot',
			'SkypeUriPreview', 'Viber', 'Line/', 'Pinterest', 'Snapchat',
		);

		if ( in_array( $signature, $social_preview, true ) ) {
			return 'social_preview_fetcher';
		}

		$category = self::get_category_for_signature( $signature );
		return $category ? $category : 'known_ai_crawler';
	}

	/**
	 * Get the bot category for a matched signature.
	 *
	 * @param string $signature Matched UA signature.
	 * @return string|null Category key or null if not mapped.
	 */
	public static function get_category_for_signature( $signature ) {
		return isset( self::$signature_categories[ $signature ] ) ? self::$signature_categories[ $signature ] : null;
	}

	/**
	 * Get the company name for a matched signature.
	 *
	 * @param string $signature Matched UA signature.
	 * @return string Company name or empty string.
	 */
	public static function get_company_for_signature( $signature ) {
		return isset( self::$signature_companies[ $signature ] ) ? self::$signature_companies[ $signature ] : '';
	}

	/**
	 * Auto-categorize an unknown bot based on UA string patterns.
	 * Checked in priority order — first match wins.
	 *
	 * @param string $user_agent The User-Agent string.
	 * @return string Category key.
	 */
	public static function auto_categorize( $user_agent ) {
		if ( false !== stripos( $user_agent, '-User' ) ) {
			return self::CAT_AI_ASSISTANTS;
		}
		if ( false !== stripos( $user_agent, 'SearchBot' ) || false !== stripos( $user_agent, '-SearchBot' ) ) {
			return self::CAT_AI_SEARCH;
		}
		if ( false !== stripos( $user_agent, 'Agent' ) || false !== stripos( $user_agent, 'Operator' ) ) {
			return self::CAT_AI_AGENTS;
		}
		if ( false !== stripos( $user_agent, '-Extended' ) || false !== stripos( $user_agent, 'training' ) || false !== stripos( $user_agent, 'dataset' ) ) {
			return self::CAT_AI_TRAINING;
		}
		if ( false !== stripos( $user_agent, 'Spider' ) || false !== stripos( $user_agent, 'Scraper' ) || false !== stripos( $user_agent, 'Scraping' ) || false !== stripos( $user_agent, 'Crawl' ) ) {
			return self::CAT_SCRAPERS;
		}
		if ( false !== stripos( $user_agent, 'Bot' ) ) {
			return self::CAT_GENERAL_AI;
		}
		return self::CAT_GENERAL_AI;
	}

	/**
	 * Get all signatures grouped by category.
	 *
	 * @return array<string, string[]> Category key => array of signature strings.
	 */
	public static function get_signatures_by_category() {
		$grouped = array();
		foreach ( self::$signature_categories as $sig => $cat ) {
			if ( ! isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ] = array();
			}
			$grouped[ $cat ][] = $sig;
		}
		return $grouped;
	}

	/**
	 * Get metadata for all signatures (for the admin UI modal).
	 *
	 * @return array[] Array of {name, category, company}.
	 */
	public static function get_all_signature_metadata() {
		$result = array();
		foreach ( self::$signature_categories as $sig => $cat ) {
			$result[] = array(
				'name'    => $sig,
				'category' => $cat,
				'company'  => isset( self::$signature_companies[ $sig ] ) ? self::$signature_companies[ $sig ] : '',
			);
		}
		return $result;
	}

	/**
	 * Get all valid category keys.
	 *
	 * @return string[]
	 */
	public static function get_category_keys() {
		return array(
			self::CAT_AI_SEARCH,
			self::CAT_AI_ASSISTANTS,
			self::CAT_AI_AGENTS,
			self::CAT_AI_TRAINING,
			self::CAT_SCRAPERS,
			self::CAT_GENERAL_AI,
		);
	}

	/**
	 * Check if a UA belongs to a legitimate search engine crawler.
	 * These crawlers use Mozilla/ UAs but don't send Sec-Fetch-* headers,
	 * so they must be exempted from header scoring to avoid false gating.
	 *
	 * @param string $user_agent The User-Agent string.
	 * @return bool
	 */
	private static function is_search_crawler( $user_agent ) {
		$search_crawlers = array(
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
		);

		foreach ( $search_crawlers as $crawler_pattern ) {
			if ( 1 === preg_match( $crawler_pattern, $user_agent ) ) {
				return true;
			}
		}

		return false;
	}
}
