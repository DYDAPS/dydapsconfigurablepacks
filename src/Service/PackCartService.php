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

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;

/**
 * Coordinates validation, pricing, persistence and cart updates for pack adds.
 */
final class PackCartService
{
    private PackCartRepository $cartRepository;
    private PackConfigurationHashGenerator $hashGenerator;
    private PackPriceCalculator $priceCalculator;
    private PackAvailabilityService $availabilityService;
    private PackConfigurationValidator $validator;

    /**
     * @param PackCartRepository $cartRepository repository used to persist cart configurations
     * @param PackConfigurationHashGenerator $hashGenerator generator for stable cart configuration hashes
     * @param PackPriceCalculator $priceCalculator calculator for unit price snapshots
     * @param PackAvailabilityService $availabilityService service enforcing component stock availability
     * @param PackConfigurationValidator $validator validator for pack definition constraints
     *
     * @return void
     */
    public function __construct(
        PackCartRepository $cartRepository,
        PackConfigurationHashGenerator $hashGenerator,
        PackPriceCalculator $priceCalculator,
        PackAvailabilityService $availabilityService,
        PackConfigurationValidator $validator,
    ) {
        $this->cartRepository = $cartRepository;
        $this->hashGenerator = $hashGenerator;
        $this->priceCalculator = $priceCalculator;
        $this->availabilityService = $availabilityService;
        $this->validator = $validator;
    }

    /**
     * Add a configured pack to the supplied PrestaShop cart.
     *
     * Side effects: validates the selection, writes the configuration and unit
     * price snapshot to the module cart table, then updates the native cart
     * quantity for the pack product.
     *
     * @param \Cart $cart cart receiving the configured pack
     * @param PackConfiguration $configuration selected pack configuration
     * @param int $idShop shop identifier used for validation and price lookup
     * @param int $idLang language identifier used for validation labels and product names
     * @param int $idCurrency currency identifier used for price context
     * @param int $idCustomer customer identifier used for specific prices
     *
     * @return string configuration hash stored with the cart row
     *
     * @throws \RuntimeException when validation fails, stock is unavailable, or the native cart cannot be updated
     */
    public function addConfiguredPack(\Cart $cart, PackConfiguration $configuration, int $idShop, int $idLang, int $idCurrency, int $idCustomer = 0): string
    {
        $configuration = $this->validator->validateAndNormalize($configuration, $idShop, $idLang);

        $hash = $this->hashGenerator->generate($configuration);
        $existing = $this->cartRepository->getCartConfigurationByHash((int) $cart->id, $configuration->getIdProduct(), 0, $hash);
        $totalQuantity = $configuration->getQuantity() + ($existing ? (int) $existing['quantity'] : 0);
        $availabilityConfiguration = new PackConfiguration($configuration->getIdProduct(), $configuration->getComponents(), $totalQuantity);
        $this->availabilityService->assertAvailable($availabilityConfiguration, $idShop);
        $price = $this->priceCalculator->calculate($configuration, $idShop, $idLang, $idCurrency, $idCustomer);
        $idCustomization = $existing ? (int) $existing['id_customization'] : $this->createNativeCustomization($cart, $configuration->getIdProduct(), 0);

        $updated = $cart->updateQty($configuration->getQuantity(), $configuration->getIdProduct(), 0, $idCustomization);
        if ($updated === false || $updated < 0) {
            if (!$existing) {
                $this->cartRepository->rollbackNativeAdd((int) $cart->id, $configuration->getIdProduct(), 0, $idCustomization);
            }
            throw new \RuntimeException('Unable to add configured pack.');
        }

        $saved = $this->cartRepository->saveConfiguration((int) $cart->id, $configuration->getIdProduct(), 0, $idCustomization, $hash, $totalQuantity, $configuration->toArray(), [
            'unit_tax_excl' => $price->unitTaxExcl,
            'unit_tax_incl' => $price->unitTaxIncl,
        ]);
        if (!$saved) {
            if (!$existing) {
                $this->cartRepository->rollbackNativeAdd((int) $cart->id, $configuration->getIdProduct(), 0, $idCustomization);
            }

            throw new \RuntimeException('Unable to save configured pack.');
        }

        return $hash;
    }

    /**
     * Create the native customization row used by PrestaShop to split cart lines.
     *
     * The module keeps the visible configuration in its own tables; this native
     * row is deliberately minimal and exists to make cart/order rows distinct.
     *
     * @param \Cart $cart cart receiving the pack
     * @param int $idProduct native pack product identifier
     * @param int $idProductAttribute native pack product combination identifier
     *
     * @return int created customization identifier
     *
     * @throws \RuntimeException when the customization cannot be created
     */
    private function createNativeCustomization(\Cart $cart, int $idProduct, int $idProductAttribute): int
    {
        $payload = [
            'id_cart' => (int) $cart->id,
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'id_address_delivery' => (int) ($cart->id_address_delivery ?? 0),
            'quantity' => 0,
            'quantity_refunded' => 0,
            'quantity_returned' => 0,
            'in_cart' => 1,
        ];

        if (!\Db::getInstance()->insert('customization', $payload)) {
            throw new \RuntimeException('Unable to create native pack customization.');
        }

        return (int) \Db::getInstance()->Insert_ID();
    }
}
