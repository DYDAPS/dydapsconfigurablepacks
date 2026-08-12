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

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}

final class Context
{
    public $shop;
    public $cookie;
    public $customer;
    public $cart;
}

final class TestCookie
{
    public int $writes = 0;
    public int $id_cart = 0;

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function write(): void
    {
        ++$this->writes;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->values[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $this->values[$name] = $value;
    }
}

final class Tools
{
    public static function hash(string $value): string
    {
        return hash('sha256', 'test-secret:' . $value);
    }
}

require_once __DIR__ . '/../../src/Security/FrontAjaxToken.php';

use Dydaps\ConfigurablePacks\Security\FrontAjaxToken;

function setFrontContext(Context $frontContext, int $idShop, ?TestCookie $cookie = null, int $idCustomer = 0, int $idCart = 0): void
{
    $cookie = $cookie ?: new TestCookie();
    if ($idCart > 0) {
        $cookie->id_cart = $idCart;
    } else {
        $cookie->id_cart = 0;
    }

    $frontContext->shop = (object) ['id' => $idShop];
    $frontContext->cookie = $cookie;
    $frontContext->customer = (object) ['id' => $idCustomer];
    $frontContext->cart = $idCart > 0 ? (object) ['id' => $idCart] : null;
}

$frontContext = new Context();
$token = new FrontAjaxToken($frontContext);

setFrontContext($frontContext, 1);
$withoutCart = $token->getToken();
assert($withoutCart !== '', 'A token must be generated before a cart exists.');
assert($frontContext->cookie->writes === 1, 'Generating the first token must persist a visitor secret.');

setFrontContext($frontContext, 1, $frontContext->cookie, 0, 123);
assert($token->getToken() === $withoutCart, 'Creating a cart must not change the token.');
assert($token->isValid($withoutCart), 'A token generated before cart creation must remain valid after cart creation.');

setFrontContext($frontContext, 1, $frontContext->cookie, 0, 456);
assert($token->getToken() === $withoutCart, 'Replacing cart A by cart B must not change the token.');
assert($token->isValid($withoutCart), 'A token must remain valid after a cart change.');

setFrontContext($frontContext, 1);
$firstAnonymousToken = $token->getToken();
setFrontContext($frontContext, 1);
$secondAnonymousToken = $token->getToken();
assert($firstAnonymousToken !== $secondAnonymousToken, 'Two anonymous visitors without carts must receive distinct tokens.');
assert(!$token->isValid($firstAnonymousToken), 'A token from another anonymous visitor must be rejected.');

setFrontContext($frontContext, 1, null, 42);
$customerToken = $token->getToken();
assert($customerToken !== '', 'A connected customer must receive a token.');
setFrontContext($frontContext, 1, $frontContext->cookie, 42, 789);
assert($token->isValid($customerToken), 'A connected customer token must remain valid after cart creation.');

setFrontContext($frontContext, 1);
$shopOneToken = $token->getToken();
setFrontContext($frontContext, 2, $frontContext->cookie);
assert(!$token->isValid($shopOneToken), 'A token scoped to one shop must not validate in another shop.');

assert(!$token->isValid(''), 'An absent front AJAX token must be rejected.');
assert(!$token->isValid('invalid'), 'An invalid front AJAX token must be rejected.');

echo "Front AJAX token tests passed.\n";
