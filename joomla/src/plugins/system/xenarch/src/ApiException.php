<?php
/**
 * Xenarch platform API error — Joomla port.
 *
 * Carries the HTTP status the platform returned (or 0 for a transport
 * failure) so callers can mirror the WordPress plugin's behaviour:
 *
 *   - 4xx  → the platform deliberately said no (fail closed).
 *   - 5xx / 0 (transport) → the platform is unreachable (fail open).
 *
 * This is the Joomla analogue of the WP_Error ``status`` data the
 * WordPress client (class-xenarch-api.php) attaches to every failure.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

class ApiException extends \RuntimeException
{
    /** @var int HTTP status code, or 0 for a transport-level failure. */
    public int $status;

    public function __construct(int $status, string $message = '')
    {
        $this->status = $status;
        parent::__construct($message !== '' ? $message : ('API call failed (HTTP ' . $status . ')'), $status);
    }

    /** True when the platform deliberately rejected the call (fail closed). */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }
}
