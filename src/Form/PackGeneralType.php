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

namespace Dydaps\ConfigurablePacks\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Config\PackConfig;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Configurable pack form.
 *
 * The form mirrors a native product sheet: catalog, pricing, SEO and packing
 * fields are persisted on the linked PrestaShop product, while pack-specific
 * settings and the component composition are stored on the pack definition.
 */
final class PackGeneralType extends TranslatorAwareType
{
    /**
     * Build the pack form.
     *
     * @param FormBuilderInterface $builder symfony form builder
     * @param array<string, mixed> $options symfony form options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');

        $builder
            ->add('id_product', HiddenType::class, [
                'required' => false,
            ])
            ->add('id_shop', HiddenType::class, [
                'required' => false,
            ])
            ->add('product_name', TextType::class, [
                'label' => $this->trans('Pack name', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('product_summary', TextareaType::class, [
                'label' => $this->trans('Summary', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
            ])
            ->add('product_description', TextareaType::class, [
                'label' => $this->trans('Description', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
            ])
            ->add('reference', TextType::class, [
                'label' => $this->trans('Reference', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'help' => $this->trans('Leave empty to generate a pack reference automatically.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('link_rewrite', TextType::class, [
                'label' => $this->trans('Friendly URL', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'help' => $this->trans('Leave empty to generate from the pack name.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('categories', ChoiceType::class, [
                'label' => $this->trans('Categories', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'multiple' => true,
                'choices' => $this->getCategoryChoices($idLang),
            ])
            ->add('default_category', ChoiceType::class, [
                'label' => $this->trans('Default category', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => $this->getCategoryChoices($idLang),
            ])
            ->add('accessories', ChoiceType::class, [
                'label' => $this->trans('Associated products', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'multiple' => true,
                'choices' => $this->getAccessoryChoices($idLang),
                'help' => $this->trans('Extra products displayed on the pack product page.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('width', NumberType::class, [
                'label' => $this->trans('Width', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
            ])
            ->add('height', NumberType::class, [
                'label' => $this->trans('Height', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
            ])
            ->add('depth', NumberType::class, [
                'label' => $this->trans('Depth', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
            ])
            ->add('weight', NumberType::class, [
                'label' => $this->trans('Weight', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
            ])
            ->add('delivery_time', TextType::class, [
                'label' => $this->trans('Delivery time in stock', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
            ])
            ->add('price_tax_excl', NumberType::class, [
                'label' => $this->trans('Price tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'scale' => 6,
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0]),
                ],
            ])
            ->add('tax_rules_group', ChoiceType::class, [
                'label' => $this->trans('Tax rules', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => $this->getTaxRuleChoices(),
            ])
            ->add('meta_title', TextType::class, [
                'label' => $this->trans('Meta title', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
            ])
            ->add('meta_description', TextType::class, [
                'label' => $this->trans('Meta description', 'Modules.Dydapsconfigurablepacks.Admin'),
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
                'constraints' => [new Range(['min' => 0])],
                'attr' => ['data-pricing-field' => PackConfig::PRICING_FIXED],
            ])
            ->add('forced_price_tax_excl', NumberType::class, [
                'label' => $this->trans('Forced final price tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [new Range(['min' => 0])],
                'attr' => ['data-pricing-field' => PackConfig::PRICING_FORCED],
            ])
            ->add('global_discount_percent', NumberType::class, [
                'label' => $this->trans('Global discount percent', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [new Range(['min' => 0, 'max' => 100])],
                'attr' => ['data-pricing-field' => PackConfig::PRICING_PERCENT_DISCOUNT],
            ])
            ->add('global_discount_amount_tax_excl', NumberType::class, [
                'label' => $this->trans('Global fixed discount tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'constraints' => [new Range(['min' => 0])],
                'attr' => ['data-pricing-field' => PackConfig::PRICING_FIXED_DISCOUNT],
            ])
            ->add('stock_behavior', ChoiceType::class, [
                'label' => $this->trans('Stock behavior', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Validate and decrement components', 'Modules.Dydapsconfigurablepacks.Admin') => 'components',
                    $this->trans('Validate components only', 'Modules.Dydapsconfigurablepacks.Admin') => 'validate_only',
                ],
            ])
            ->add('components_json', HiddenType::class, [
                'label' => $this->trans('Pack component definition', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
            ]);
    }

    /**
     * Configure translation and CSRF defaults for the form type.
     *
     * @param OptionsResolver $resolver symfony options resolver
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

    /**
     * Flatten the shop category tree into a flat labelled choice list.
     *
     * @param int $idLang language identifier
     *
     * @return array<string, int> label to category identifier map
     */
    private function getCategoryChoices(int $idLang): array
    {
        $choices = [];
        $walk = function (array $categories, string $prefix = '') use (&$walk, &$choices): void {
            foreach ($categories as $category) {
                $label = $prefix === '' ? (string) $category['name'] : $prefix . ' > ' . $category['name'];
                $choices[$label] = (int) $category['id_category'];
                if (!empty($category['children'])) {
                    $walk($category['children'], $label);
                }
            }
        };
        $walk(\Category::getCategories($idLang, true));

        return $choices;
    }

    /**
     * Build the list of active products usable as associated products.
     *
     * @param int $idLang language identifier
     *
     * @return array<string, int> label to product identifier map
     */
    private function getAccessoryChoices(int $idLang): array
    {
        $choices = [];
        foreach (\Product::getProducts($idLang, 0, 0, 'name', 'asc', false, true) as $product) {
            $label = (string) ($product['name'] ?? '');
            if (isset($product['reference']) && trim((string) $product['reference']) !== '') {
                $label .= ' (' . $product['reference'] . ')';
            }
            $choices[$label] = (int) $product['id_product'];
        }

        return $choices;
    }

    /**
     * Build the list of tax rules groups.
     *
     * @return array<string, int> label to tax rules group identifier map
     */
    private function getTaxRuleChoices(): array
    {
        $choices = [];
        foreach (\TaxRulesGroup::getTaxRulesGroups(true) as $group) {
            $choices[(string) $group['name']] = (int) $group['id_tax_rules_group'];
        }

        return $choices;
    }
}
