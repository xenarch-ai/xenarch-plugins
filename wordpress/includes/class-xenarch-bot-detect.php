<?php
/**
 * Xenarch bot detection.
 *
 * Detects AI bots via three tiers:
 * - Tier A1: Known AI crawler User-Agent signatures (100+)
 * - Tier A2: HTTP client library signatures (definitive bot indicators)
 * - Tier B:  Header scoring for browser-spoofing detection
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

		// Apple
		'Applebot',
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

		// Social / Preview Bots
		'Twitterbot',
		'LinkedInBot',
		'WhatsApp',
		'TelegramBot',
		'Slackbot',
		'Discordbot',

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
	 * Header scoring threshold for browser-spoofing detection.
	 * Score >= this value = bot.
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
	 * Full detection with header scoring.
	 * Returns rich result with method, score, and signal details.
	 *
	 * @param string $user_agent The User-Agent header value.
	 * @param array  $headers    Associative array of HTTP headers.
	 * @return array Detection result: is_bot, method, score, signal.
	 */
	public static function detect_full( $user_agent, $headers = array() ) {
		// 1. Empty UA → instant bot.
		if ( empty( $user_agent ) ) {
			return array(
				'is_bot' => true,
				'method' => 'empty_ua',
				'score'  => 0,
				'signal' => 'empty_ua',
			);
		}

		// 2. AI crawler signature match → instant bot.
		foreach ( self::$signatures as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				return array(
					'is_bot' => true,
					'method' => 'ua_match',
					'score'  => 0,
					'signal' => $signature,
				);
			}
		}

		// 3. HTTP client library match → instant bot.
		foreach ( self::$fetcher_signatures as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				return array(
					'is_bot' => true,
					'method' => 'fetcher_ua',
					'score'  => 0,
					'signal' => $signature,
				);
			}
		}

		// 4. Browser-claiming UA → run header scoring (Tier B).
		if ( false !== stripos( $user_agent, 'Mozilla/' ) ) {
			$score   = 0;
			$signals = array();

			// Accept: is */* or missing → +2.
			$accept = isset( $headers['Accept'] ) ? $headers['Accept'] : '';
			if ( empty( $accept ) || '*/*' === trim( $accept ) ) {
				$score += 2;
				$signals[] = 'accept';
			}

			// Accept-Language: missing or empty → +2.
			$accept_lang = isset( $headers['Accept-Language'] ) ? $headers['Accept-Language'] : '';
			if ( empty( $accept_lang ) ) {
				$score += 2;
				$signals[] = 'accept-language';
			}

			// Sec-Fetch-Mode: missing → +2.
			if ( empty( $headers['Sec-Fetch-Mode'] ) ) {
				$score += 2;
				$signals[] = 'sec-fetch-mode';
			}

			// Sec-Fetch-Dest: missing → +1.
			if ( empty( $headers['Sec-Fetch-Dest'] ) ) {
				$score += 1;
				$signals[] = 'sec-fetch-dest';
			}

			// Sec-Fetch-Site: missing → +1.
			if ( empty( $headers['Sec-Fetch-Site'] ) ) {
				$score += 1;
				$signals[] = 'sec-fetch-site';
			}

			// Sec-Ch-Ua: missing when UA claims Chrome/Edge → +1.
			if ( empty( $headers['Sec-Ch-Ua'] ) && ( false !== stripos( $user_agent, 'Chrome' ) || false !== stripos( $user_agent, 'Edg' ) ) ) {
				$score += 1;
				$signals[] = 'sec-ch-ua';
			}

			// Accept-Encoding: missing brotli → +1.
			$accept_enc = isset( $headers['Accept-Encoding'] ) ? $headers['Accept-Encoding'] : '';
			if ( false === stripos( $accept_enc, 'br' ) ) {
				$score += 1;
				$signals[] = 'accept-encoding';
			}

			if ( $score >= self::HEADER_SCORE_THRESHOLD ) {
				return array(
					'is_bot' => true,
					'method' => 'header_score:' . $score,
					'score'  => $score,
					'signal' => implode( ',', $signals ),
				);
			}

			return array(
				'is_bot' => false,
				'method' => 'browser',
				'score'  => $score,
				'signal' => '',
			);
		}

		// 5. Unknown UA (not browser, not bot) → pass through.
		return array(
			'is_bot' => false,
			'method' => 'unknown',
			'score'  => 0,
			'signal' => '',
		);
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
}
