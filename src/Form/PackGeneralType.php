<?php
/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * @author    DYDAPS
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Config\PackConfig;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * General pack configuration form.
 *
 * This type covers the first indispensable scope: activation, linked pack
 * product, pricing method and stock behavior. Composition is intentionally
 * persisted through dedicated component tables and services.
 */
final class PackGeneralType extends TranslatorAwareType
{
    /**
     * Build the general pack settings form.
     *
     * @param FormBuilderInterface $builder Symfony form builder.
     * @param array<string, mixed> $options Symfony form options.
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_product', IntegerType::class, [
                'label' => $this->trans('Pack product ID', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan(['value' => 0]),
                ],
                'help' => $this->trans('Native PrestaShop product that will be sold as a configurable pack.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('active', CheckboxType::class, [
                'label' => $this->trans('Enable configurable-pack mode', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
            ])
            ->add('pack_type', ChoiceType::class, [
                'label' => $this->trans('Pack type', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Fixed components', 'Modules.Dydapsconfigurablepacks.Admin') => 'fixed',
                    $this->trans('Choice groups', 'Modules.Dydapsconfigurablepacks.Admin') => 'choice',
                    $this->trans('Steps', 'Modules.Dydapsconfigurablepacks.Admin') => 'steps',
                ],
            ])
            ->add('pricing_method', ChoiceType::class, [
                'label' => $this->trans('Pricing method', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Fixed price', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FIXED,
                    $this->trans('Component sum', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_COMPONENT_SUM,
                    $this->trans('Percentage discount', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_PERCENT_DISCOUNT,
                    $this->trans('Fixed discount', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FIXED_DISCOUNT,
                    $this->trans('Forced price', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FORCED,
                ],
            ])
            ->add('fixed_price_tax_excl', NumberType::class, [
                'label' => $this->trans('Fixed price tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [
                    new Range(['min' => 0]),
                ],
            ])
            ->add('forced_price_tax_excl', NumberType::class, [
                'label' => $this->trans('Forced final price tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [
                    new Range(['min' => 0]),
                ],
            ])
            ->add('global_discount_percent', NumberType::class, [
                'label' => $this->trans('Global discount percent', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [
                    new Range(['min' => 0, 'max' => 100]),
                ],
            ])
            ->add('global_discount_amount_tax_excl', NumberType::class, [
                'label' => $this->trans('Global fixed discount tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [
                    new Range(['min' => 0]),
                ],
            ])
            ->add('stock_behavior', ChoiceType::class, [
                'label' => $this->trans('Stock behavior', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Validate and decrement components', 'Modules.Dydapsconfigurablepacks.Admin') => 'components',
                    $this->trans('Validate components only', 'Modules.Dydapsconfigurablepacks.Admin') => 'validate_only',
                ],
            ])
            ->add('components_json', TextareaType::class, [
                'label' => $this->trans('Pack component definition', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'attr' => ['rows' => 16, 'class' => 'monospace'],
                'help' => $this->trans('Define components, allowed products, allowed combinations, defaults, quantities and pricing behavior as JSON.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ]);
    }

    /**
     * Configure translation and CSRF defaults for the form type.
     *
     * @param OptionsResolver $resolver Symfony options resolver.
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'Modules.Dydapsconfigurablepacks.Admin',
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'dydaps.configurable_packs.general.save',
        ]);
    }
}
