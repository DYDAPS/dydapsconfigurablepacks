<?php
/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    DYDAPS
 * @copyright 2007-2026 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Security;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates and validates front-office tokens for mutating AJAX actions.
 */
final class FrontAjaxToken
{
    private const COOKIE_SECRET_KEY = 'dydaps_configurable_packs_ajax_secret';

    private \Context $context;

    /**
     * @param \Context $context injected legacy context used to scope tokens to shop and cookie
     *
     * @return void
     */
    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    /**
     * Build the token for the current visitor context.
     *
     * @return string context-bound token, or an empty string when PrestaShop is not booted
     */
    public function getToken(): string
    {
        if (!class_exists('\Context') || !class_exists('\Tools')) {
            return '';
        }

        $secret = $this->getOrCreateVisitorSecret();

        return $secret !== '' ? $this->buildToken($secret) : '';
    }

    /**
     * Return whether a submitted token matches the current visitor context.
     *
     * @param string $token submitted token
     *
     * @return bool true only for the current context token
     */
    public function isValid(string $token): bool
    {
        if (!class_exists('\Context') || !class_exists('\Tools')) {
            return false;
        }

        $secret = $this->getVisitorSecret();
        $expected = $secret !== '' ? $this->buildToken($secret) : '';

        return $token !== '' && $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * Build the scoped token from the per-visitor secret and current shop.
     *
     * @param string $secret stable per-visitor secret from the PrestaShop cookie
     *
     * @return string token scoped to this module, action and shop
     */
    private function buildToken(string $secret): string
    {
        $shop = $this->context->shop ?? null;

        return \Tools::hash(implode(':', [
            'dydapsconfigurablepacks',
            'front',
            'add',
            (int) ($shop->id ?? 0),
            $secret,
        ]));
    }

    /**
     * Return the current visitor secret, creating it when missing.
     *
     * @return string stable random secret
     */
    private function getOrCreateVisitorSecret(): string
    {
        $secret = $this->getVisitorSecret();
        if ($secret !== '') {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        $cookie = $this->context->cookie ?? null;
        if (!$cookie) {
            return '';
        }

        $cookie->__set(self::COOKIE_SECRET_KEY, $secret);
        if (method_exists($cookie, 'write')) {
            $cookie->write();
        }

        return $secret;
    }

    /**
     * Read a previously created visitor secret from the PrestaShop cookie.
     *
     * @return string existing random secret, or an empty string when unavailable
     */
    private function getVisitorSecret(): string
    {
        $cookie = $this->context->cookie ?? null;
        if (!$cookie) {
            return '';
        }

        $secret = (string) $cookie->__get(self::COOKIE_SECRET_KEY);

        return preg_match('/\A[a-f0-9]{64}\z/', $secret) === 1 ? $secret : '';
    }
}
