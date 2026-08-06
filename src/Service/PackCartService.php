<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackCartRepository;
use Dydaps\ConfigurablePacks\Validator\PackConfigurationValidator;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackCartService
{
    private PackCartRepository $cartRepository;
    private PackConfigurationHashGenerator $hashGenerator;
    private PackPriceCalculator $priceCalculator;
    private PackAvailabilityService $availabilityService;
    private PackConfigurationValidator $validator;

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
