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
		return array(
			'decision'      => $decision,
			'traffic_class' => $traffic_class,
			'method'        => $method,
			'score'         => $score,
			'signals'       => $signals,
			'signal'        => implode( ',', $signals ),
			'confidence'    => 'block' === $decision ? 'high' : ( 'challenge' === $decision ? 'medium' : 'high' ),
			'is_bot'        => 'allow' !== $decision,
		);
	}

	/**
	 * Infer traffic class from a matched signature.
	 *
	 * @param string $signature Matched user-agent signature.
	 * @return string
	 */
	private static function traffic_class_for_signature( $signature ) {
		$social_preview = array(
			'FacebookBot',
			'facebookexternalhit',
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
		);

		if ( in_array( $signature, $social_preview, true ) ) {
			return 'social_preview_fetcher';
		}

		return 'known_ai_crawler';
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
