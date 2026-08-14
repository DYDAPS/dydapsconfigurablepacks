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
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;
use PrestaShop\PrestaShop\Adapter\LegacyContext;

/**
 * Coordinates validation, pricing, persistence and cart updates for pack adds.
 */
final class PackCartService
{
    private const CUSTOMIZED_DATA_VALUE_LIMIT = 1024;

    private PackCartRepository $cartRepository;
    private PackConfigurationHashGenerator $hashGenerator;
    private PackPriceCalculator $priceCalculator;
    private PackAvailabilityService $availabilityService;
    private PackConfigurationValidator $validator;
    private PackRepository $packRepository;
    private LegacyContext $legacyContext;

    /**
     * @param PackCartRepository $cartRepository repository used to persist cart configurations
     * @param PackConfigurationHashGenerator $hashGenerator generator for stable cart configuration hashes
     * @param PackPriceCalculator $priceCalculator calculator for unit price snapshots
     * @param PackAvailabilityService $availabilityService service enforcing component stock availability
     * @param PackConfigurationValidator $validator validator for pack definition constraints
     * @param PackRepository $packRepository repository used to read pack stock settings
     * @param LegacyContext $legacyContext adapter used to translate the native pack customization label
     *
     * @return void
     */
    public function __construct(
        PackCartRepository $cartRepository,
        PackConfigurationHashGenerator $hashGenerator,
        PackPriceCalculator $priceCalculator,
        PackAvailabilityService $availabilityService,
        PackConfigurationValidator $validator,
        PackRepository $packRepository,
        LegacyContext $legacyContext,
    ) {
        $this->cartRepository = $cartRepository;
        $this->hashGenerator = $hashGenerator;
        $this->priceCalculator = $priceCalculator;
        $this->availabilityService = $availabilityService;
        $this->validator = $validator;
        $this->packRepository = $packRepository;
        $this->legacyContext = $legacyContext;
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
        $pack = $this->packRepository->getPackByProduct($configuration->getIdProduct(), $idShop);
        $this->availabilityService->assertAvailable($availabilityConfiguration, $idShop, $pack !== null && (int) ($pack['allow_oos_order'] ?? 0) === 1);
        $price = $this->priceCalculator->calculate($configuration, $idShop, $idLang, $idCurrency, $idCustomer);
        $idCustomization = $existing ? (int) $existing['id_customization'] : $this->createNativeCustomization($cart, $configuration, $idLang, $idShop);

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
     * The pack product receives a module-managed native customization field, and
     * every configured pack line writes one customized_data row whose value is a
     * short human-readable summary. PrestaShop's cart presenter only builds
     * add/update/delete URLs carrying an id_customization when customized data
     * exists, so this row is what makes cart line operations reliable.
     *
     * @param \Cart $cart cart receiving the pack
     * @param PackConfiguration $configuration validated pack configuration
     * @param int $idLang language used for the summary labels
     * @param int $idShop shop used for product name resolution
     *
     * @return int created customization identifier
     *
     * @throws \RuntimeException when the customization cannot be created
     */
    private function createNativeCustomization(\Cart $cart, PackConfiguration $configuration, int $idLang, int $idShop): int
    {
        $idProduct = $configuration->getIdProduct();
        $idProductAttribute = 0;
        $idField = $this->cartRepository->ensurePackCustomizationField($idProduct, $this->buildPackFieldNames());

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
        $idCustomization = (int) \Db::getInstance()->Insert_ID();

        $value = $this->buildNativeCustomizationValue($configuration, $idLang, $idShop);
        if ($value !== '') {
            if (!\Db::getInstance()->insert('customized_data', [
                'id_customization' => $idCustomization,
                'type' => \Product::CUSTOMIZE_TEXTFIELD,
                'index' => $idField,
                'value' => pSQL($value),
                'id_module' => 0,
                'price' => '0',
                'weight' => '0',
            ])) {
                $this->cartRepository->deleteNativeCustomization($idCustomization);
                throw new \RuntimeException('Unable to store native pack customization data.');
            }
        }

        return $idCustomization;
    }

    /**
     * Build localized names for the pack customization field.
     *
     * @return array<int, string> localized field names indexed by language id
     */
    private function buildPackFieldNames(): array
    {
        $names = [];
        $translator = $this->legacyContext->getContext()->getTranslator();
        foreach (\Language::getLanguages(false) as $lang) {
            $names[(int) $lang['id_lang']] = (string) $translator->trans('Pack configuration', [], 'Modules.Dydapsconfigurablepacks.Shop');
        }

        return $names;
    }

    /**
     * Build the short summary stored in the native customized_data row.
     *
     * The value must fit in the customized_data value column, so the summary is
     * truncated while keeping the leading component list readable.
     *
     * @param PackConfiguration $configuration validated pack configuration
     * @param int $idLang language used for labels
     * @param int $idShop shop used for product name resolution
     *
     * @return string summary value, or an empty string when nothing can be resolved
     */
    private function buildNativeCustomizationValue(PackConfiguration $configuration, int $idLang, int $idShop): string
    {
        $parts = [];
        foreach ($configuration->getComponents() as $component) {
            $idProduct = (int) $component['id_product'];
            if ($idProduct <= 0) {
                continue;
            }

            $product = new \Product($idProduct, false, $idLang, $idShop);
            $label = \Validate::isLoadedObject($product) ? (string) $product->name : ('Product #' . $idProduct);
            $idAttribute = (int) ($component['id_product_attribute'] ?? 0);
            if ($idAttribute > 0) {
                $label .= ' - ' . strip_tags(\Product::getProductName($idProduct, $idAttribute, $idLang));
            }
            $label .= ' x' . max(1, (int) ($component['quantity'] ?? 1));

            $details = [];
            $freeText = trim((string) ($component['customization'] ?? ''));
            if ($freeText !== '') {
                $details[] = $freeText;
            }
            foreach ((array) ($component['customization_fields'] ?? []) as $field) {
                $value = trim((string) $field['value']);
                if ($value === '') {
                    continue;
                }
                $name = $this->cartRepository->getCustomizationFieldName((int) $field['id_customization_field'], $idLang, $idShop);
                $details[] = ($name !== '' ? $name . ': ' : '') . $value;
            }
            if ($details) {
                $label .= ' (' . implode('; ', $details) . ')';
            }

            $parts[] = $label;
        }

        $summary = implode(' | ', $parts);

        return mb_substr($summary, 0, self::CUSTOMIZED_DATA_VALUE_LIMIT);
    }
}
