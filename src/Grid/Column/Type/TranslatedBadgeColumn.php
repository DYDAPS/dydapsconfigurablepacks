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

namespace Dydaps\ConfigurablePacks\Grid\Column\Type;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Grid\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Renders a raw column value as a translated colored badge.
 */
final class TranslatedBadgeColumn extends AbstractColumn
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'translated_badge';
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired(['field'])
            ->setDefaults([
                'labels' => [],
                'badge_types' => [],
                'default_badge_type' => 'info',
                'empty_value' => '',
            ])
            ->setAllowedTypes('field', 'string')
            ->setAllowedTypes('labels', 'array')
            ->setAllowedTypes('badge_types', 'array')
            ->setAllowedTypes('default_badge_type', 'string')
            ->setAllowedTypes('empty_value', 'string');
    }
}
