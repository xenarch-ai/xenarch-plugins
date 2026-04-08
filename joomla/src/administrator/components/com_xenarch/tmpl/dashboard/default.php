<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;

$mediaUrl = Uri::root() . 'media/com_xenarch';

// Build initial settings snapshot (same shape as WordPress).
$params = XenarchHelper::getAllParams();
$botCategories = json_decode($params['bot_categories'] ?? '{}', true);
$botOverrides = json_decode($params['bot_overrides'] ?? '{}', true);

// Count bot signatures by loading the BotDetect class.
$botSignatureCount = 0;
if (class_exists('Xenarch\\Plugin\\System\\Xenarch\\BotDetect')) {
    $botSignatureCount = count(\Xenarch\Plugin\System\Xenarch\BotDetect::getSignatures())
        + count(\Xenarch\Plugin\System\Xenarch\BotDetect::getFetcherSignatures());
}

$siteUrl = XenarchHelper::getSiteUrl();
$version = '1.0.0';

$initialSettings = [
    'api_key'             => !empty($params['api_key'] ?? ''),
    'site_id'             => $params['site_id'] ?? '',
    'site_token'          => $params['site_token'] ?? '',
    'default_price'       => $params['default_price'] ?? '0.003',
    'payout_wallet'       => $params['payout_wallet'] ?? '',
    'gate_unknown_traffic' => $params['gate_unknown_traffic'] ?? '1',
    'gate_enabled'        => $params['gate_enabled'] ?? '1',
    'bot_categories'      => is_array($botCategories) ? $botCategories : [],
    'bot_overrides'       => is_array($botOverrides) ? $botOverrides : [],
    'wallet_type'         => $params['wallet_type'] ?? '',
    'wallet_network'      => $params['wallet_network'] ?? 'base',
    'domain'              => XenarchHelper::getSiteDomain(),
    'has_wallet'          => !empty($params['payout_wallet'] ?? ''),
    'has_site'            => !empty($params['site_id'] ?? '') && !empty($params['site_token'] ?? ''),
    'bot_signature_count' => $botSignatureCount,
    'pay_json_url'        => $siteUrl . '/.well-known/pay.json',
    'xenarch_md_url'      => $siteUrl . '/.well-known/xenarch.md',
];

// WalletConnect + CDP project IDs — fetched from platform API, cached 24h.
$wcProjectId = XenarchHelper::getWcProjectId();
$cdpProjectId = XenarchHelper::getCdpProjectId();

$config = [
    'restUrl'        => Uri::root() . 'administrator/index.php?option=com_xenarch&format=json',
    'nonce'          => Session::getFormToken(),
    'csrfHeaderName' => 'X-CSRF-Token',
    'settings'       => $initialSettings,
    'pluginUrl'      => Uri::root() . 'media/com_xenarch/',
    'version'        => $version,
    'wcProjectId'    => $wcProjectId,
    'cdpProjectId'   => $cdpProjectId,
];
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&family=Space+Grotesk:wght@300;500;600&display=swap">
<link rel="stylesheet" href="<?php echo $mediaUrl; ?>/css/xenarch-admin.css">
<div class="xenarch-wrap xenarch-page">
    <div class="xenarch-page-container">
        <div class="xenarch-page-header">
            <span class="xenarch-page-logo">/xenarch</span>
            <span class="xenarch-page-version">v<?php echo htmlspecialchars($version); ?></span>
            <button class="xenarch-page-theme-toggle" onclick="xenarchToggleTheme()" title="Toggle theme" style="margin-left:auto;">
                <svg class="xn-icon-sun" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="3"/><path d="M8 1.5v1.5M8 13v1.5M2.75 2.75l1.06 1.06M12.19 12.19l1.06 1.06M1.5 8H3M13 8h1.5M2.75 13.25l1.06-1.06M12.19 3.81l1.06-1.06"/></svg>
                <svg class="xn-icon-moon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.2 9.5a5.5 5.5 0 01-7-7 5.5 5.5 0 106.7 6.7z"/></svg>
            </button>
        </div>
        <p class="xenarch-page-subtitle">Charge the bots that take. Let through the ones that help.</p>
        <script>
            window.xenarchAdmin = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
            function xenarchToggleTheme() {
                var el = document.getElementById('xenarch-admin');
                if (!el) return;
                var t = el.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                el.setAttribute('data-theme', t);
                localStorage.setItem('xenarch-theme', t);
                document.body.classList.toggle('xenarch-light', t === 'light');
            }
        </script>
        <div id="xenarch-admin"></div>
        <script type="module" src="<?php echo $mediaUrl; ?>/js/xenarch-admin.js"></script>
    </div>
</div>
