<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
     * @param PackCartRepository $cartRepository Repository used to persist cart configurations.
     * @param PackConfigurationHashGenerator $hashGenerator Generator for stable cart configuration hashes.
     * @param PackPriceCalculator $priceCalculator Calculator for unit price snapshots.
     * @param PackAvailabilityService $availabilityService Service enforcing component stock availability.
     * @param PackConfigurationValidator $validator Validator for pack definition constraints.
     *
     * @return void
     */
    public function __construct(
        PackCartRepository $cartRepository,
        PackConfigurationHashGenerator $hashGenerator,
        PackPriceCalculator $priceCalculator,
        PackAvailabilityService $availabilityService,
        PackConfigurationValidator $validator
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
     * @param \Cart $cart Cart receiving the configured pack.
     * @param PackConfiguration $configuration Selected pack configuration.
     * @param int $idShop Shop identifier used for validation and price lookup.
     * @param int $idLang Language identifier used for validation labels and product names.
     * @param int $idCurrency Currency identifier used for price context.
     * @param int $idCustomer Customer identifier used for specific prices.
     *
     * @return string Configuration hash stored with the cart row.
     *
     * @throws \RuntimeException When validation fails, stock is unavailable, or the native cart cannot be updated.
     */
    public function addConfiguredPack(\Cart $cart, PackConfiguration $configuration, int $idShop, int $idLang, int $idCurrency, int $idCustomer = 0): string
    {
        $errors = $this->validator->validate($configuration, $idShop, $idLang);
        if ($errors) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        $this->availabilityService->assertAvailable($configuration, $idShop);
        $price = $this->priceCalculator->calculate($configuration, $idShop, $idLang, $idCurrency, $idCustomer);
        $hash = $this->hashGenerator->generate($configuration);

        $this->cartRepository->saveConfiguration((int) $cart->id, $configuration->getIdProduct(), 0, $hash, $configuration->toArray(), [
            'unit_tax_excl' => $price->unitTaxExcl,
            'unit_tax_incl' => $price->unitTaxIncl,
        ]);

        if (!$cart->updateQty($configuration->getQuantity(), $configuration->getIdProduct(), 0)) {
            throw new \RuntimeException('Unable to add configured pack.');
        }

        return $hash;
    }
}
