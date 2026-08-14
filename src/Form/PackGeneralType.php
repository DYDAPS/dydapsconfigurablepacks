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
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Configurable pack form.
 *
 * The form mirrors a native product sheet: catalog, shipping, pricing, SEO and
 * packing fields are persisted on the linked PrestaShop product, while
 * pack-specific settings and the component composition are stored on the pack
 * definition. Content fields are multilingual through TranslatableType.
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
        $categoryChoices = $options['category_choices'] ?: $this->getCategoryChoices($idLang);
        $taxRuleChoices = $options['tax_rule_choices'] ?: $this->getTaxRuleChoices();

        $builder
            ->add('cover_image', FileType::class, [
                'label' => $this->trans('Cover image', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'mapped' => false,
                'help' => $this->trans('Upload a cover image for the pack product. The current cover is shown below.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('id_product', HiddenType::class, [
                'required' => false,
            ])
            ->add('id_shop', HiddenType::class, [
                'required' => false,
            ])
            ->add('product_name', TranslatableType::class, [
                'label' => $this->trans('Pack name', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'attr' => [
                        'class' => 'dydaps-pack-name',
                        'data-name-default-lang' => (string) $idLang,
                    ],
                ],
            ])
            ->add('product_summary', TranslatableType::class, [
                'label' => $this->trans('Summary', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextareaType::class,
                'options' => [
                    'required' => false,
                    'attr' => [
                        'rows' => 4,
                    ],
                ],
            ])
            ->add('product_description', TranslatableType::class, [
                'label' => $this->trans('Description', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextareaType::class,
                'options' => [
                    'required' => false,
                    'attr' => [
                        'rows' => 8,
                    ],
                ],
            ])
            ->add('reference', TextType::class, [
                'label' => $this->trans('Reference', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'help' => $this->trans('Leave empty to generate a pack reference automatically.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('categories', ChoiceType::class, [
                'label' => $this->trans('Categories', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'multiple' => true,
                'choices' => $categoryChoices,
                'attr' => ['class' => 'dydaps-categories-field'],
            ])
            ->add('default_category', ChoiceType::class, [
                'label' => $this->trans('Default category', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'placeholder' => $this->trans('First selected category', 'Modules.Dydapsconfigurablepacks.Admin'),
                'choices' => $categoryChoices,
            ])
            ->add('accessories', ChoiceType::class, [
                'label' => $this->trans('Associated products', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'multiple' => true,
                'choices' => $this->getAccessoryChoices($idLang),
                'attr' => ['class' => 'dydaps-accessories-field'],
            ])
            ->add('width', NumberType::class, [
                'label' => $this->trans('Width', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
                'scale' => 6,
            ])
            ->add('height', NumberType::class, [
                'label' => $this->trans('Height', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
                'scale' => 6,
            ])
            ->add('depth', NumberType::class, [
                'label' => $this->trans('Depth', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
                'scale' => 6,
            ])
            ->add('weight', NumberType::class, [
                'label' => $this->trans('Weight', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'constraints' => [new Range(['min' => 0])],
                'scale' => 6,
            ])
            ->add('delivery_time_type', ChoiceType::class, [
                'label' => $this->trans('Delivery time', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('No delivery time', 'Modules.Dydapsconfigurablepacks.Admin') => 'none',
                    $this->trans('Default shop delivery time', 'Modules.Dydapsconfigurablepacks.Admin') => 'default',
                    $this->trans('Specific delivery times for this pack', 'Modules.Dydapsconfigurablepacks.Admin') => 'specific',
                ],
            ])
            ->add('delivery_in_stock', TranslatableType::class, [
                'label' => $this->trans('Delivery time for in-stock products', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                ],
            ])
            ->add('delivery_out_stock', TranslatableType::class, [
                'label' => $this->trans('Delivery time for out-of-stock products (order allowed)', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                ],
            ])
            ->add('price_tax_excl', NumberType::class, [
                'label' => $this->trans('Price tax excluded', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'attr' => ['data-price-ht'],
            ])
            ->add('price_tax_incl', NumberType::class, [
                'label' => $this->trans('Price tax included', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'scale' => 6,
                'attr' => ['data-price-ttc'],
            ])
            ->add('tax_rules_group', ChoiceType::class, [
                'label' => $this->trans('Tax rules', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => $taxRuleChoices,
            ])
            ->add('meta_title', TranslatableType::class, [
                'label' => $this->trans('Meta title', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'attr' => ['maxlength' => 70],
                ],
            ])
            ->add('meta_description', TranslatableType::class, [
                'label' => $this->trans('Meta description', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextareaType::class,
                'options' => [
                    'required' => false,
                    'attr' => ['rows' => 3, 'maxlength' => 160],
                ],
            ])
            ->add('link_rewrite', TranslatableType::class, [
                'label' => $this->trans('Friendly URL', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => false,
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'attr' => ['class' => 'dydaps-friendly-url'],
                ],
                'help' => $this->trans('Leave empty to generate from the pack name.', 'Modules.Dydapsconfigurablepacks.Admin'),
            ])
            ->add('pack_type', ChoiceType::class, [
                'label' => $this->trans('Pack type', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Fixed components', 'Modules.Dydapsconfigurablepacks.Admin') => 'fixed',
                ],
            ])
            ->add('pricing_method', ChoiceType::class, [
                'label' => $this->trans('Pricing method', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Fixed price', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FIXED,
                    $this->trans('Component sum', 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_COMPONENT_SUM,
                ],
            ])
            ->add('stock_behavior', ChoiceType::class, [
                'label' => $this->trans('Stock behavior', 'Modules.Dydapsconfigurablepacks.Admin'),
                'required' => true,
                'choices' => [
                    $this->trans('Validate and decrement components', 'Modules.Dydapsconfigurablepacks.Admin') => 'components',
                    $this->trans('Do not decrement component stock', 'Modules.Dydapsconfigurablepacks.Admin') => 'validate_only',
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
            'category_choices' => [],
            'tax_rule_choices' => [],
        ]);
        $resolver->setAllowedTypes('category_choices', ['array']);
        $resolver->setAllowedTypes('tax_rule_choices', ['array']);
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
        $walk(\Category::getNestedCategories((int) \Configuration::get('PS_HOME_CATEGORY'), $idLang, true));

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
        $rows = \Db::getInstance()->executeS(
            'SELECT g.id_tax_rules_group, g.name, g.active
             FROM `' . _DB_PREFIX_ . 'tax_rules_group` g
             WHERE g.deleted = 0
             ORDER BY g.active DESC, g.name ASC'
        );
        $choices = [];
        $used = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $label = (string) ($row['name'] ?? '');
            if ($label === '') {
                $label = '#' . (int) $row['id_tax_rules_group'];
            }
            if (isset($used[$label])) {
                $label .= ' (#' . (int) $row['id_tax_rules_group'] . ')';
            }
            $used[$label] = true;
            $choices[$label] = (int) $row['id_tax_rules_group'];
        }

        $defaultId = (int) \Configuration::get('PS_TAX_RULES_GROUP_DEFAULT');
        if ($defaultId > 0 && !in_array($defaultId, $choices, true)) {
            $row = \Db::getInstance()->getRow(
                'SELECT g.id_tax_rules_group, g.name
                 FROM `' . _DB_PREFIX_ . 'tax_rules_group` g
                 WHERE g.id_tax_rules_group = ' . $defaultId
            );
            if (is_array($row)) {
                $label = (string) ($row['name'] ?? ('#' . (int) $row['id_tax_rules_group']));
                $choices[$label] = (int) $row['id_tax_rules_group'];
            }
        }

        return $choices;
    }
}
