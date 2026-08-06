<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Controller\Admin;

use Dydaps\ConfigurablePacks\Grid\Definition\Factory\PackGridDefinitionFactory;
use Dydaps\ConfigurablePacks\Grid\Filters\PackFilters;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Form\PackGeneralType;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackController extends AbstractDydapsAdminController
{
    private GridFactoryInterface $gridFactory;
    private PackRepository $repository;

    public function __construct(GridFactoryInterface $gridFactory, GridPresenterInterface $gridPresenter, PackRepository $repository, $translator)
    {
        $this->gridFactory = $gridFactory;
        $this->setGridPresenter($gridPresenter);
        $this->repository = $repository;
        $this->setTranslator($translator);
    }

    public function index(Request $request): Response
    {
        $this->denyRead($request);
        $gridQuery = $this->getArrayParameter($request, 'query', PackGridDefinitionFactory::GRID_ID);
        $filters = new PackFilters($gridQuery);
        $grid = $this->gridFactory->getGrid($filters);

        return $this->render('@Modules/dydapsconfigurablepacks/views/templates/admin/packs.html.twig', [
            'layoutTitle' => $this->t('Configurable Packs', 'Modules.Dydapsconfigurablepacks.Admin'),
            'active' => 'packs',
            'grid' => $this->presentGrid($grid),
            'csrfToken' => $this->get('security.csrf.token_manager')->getToken('dydaps.configurable_packs.write')->getValue(),
        ]);
    }

    public function search(Request $request): RedirectResponse
    {
        $this->denyRead($request);

        $payload = $this->getArrayParameter($request, 'request', PackGridDefinitionFactory::GRID_ID);
        $filters = $payload['filters'] ?? $payload;
        $filters = is_array($filters) ? $filters : [];
        unset($filters['_token'], $filters['actions']);

        return $this->redirectToRoute('dydaps_configurable_packs_index', [
            PackGridDefinitionFactory::GRID_ID => [
                'filters' => [
                    'id_pack' => isset($filters['id_pack']) ? (string) $filters['id_pack'] : '',
                    'search' => isset($filters['search']) ? (string) $filters['search'] : '',
                    'pricing_method' => isset($filters['pricing_method']) ? (string) $filters['pricing_method'] : '',
                    'active' => isset($filters['active']) ? (string) $filters['active'] : '',
                ],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->denyUpdate($request);

        return $this->handleForm($request, null);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->denyUpdate($request);
        $pack = $this->repository->getPack($id);
        if (!$pack) {
            $this->addFlash('error', $this->t('Pack configuration not found.', 'Modules.Dydapsconfigurablepacks.Admin'));

            return $this->redirectToRoute('dydaps_configurable_packs_index');
        }

        return $this->handleForm($request, $pack);
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->denyUpdate($request);
        $this->assertValidCsrf($request, 'dydaps.configurable_packs.write');
        \Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'dydaps_pack` SET active = IF(active = 1, 0, 1), updated_at = NOW() WHERE id_pack = ' . (int) $id);
        $this->addFlash('success', $this->t('Pack status updated.', 'Modules.Dydapsconfigurablepacks.Admin'));

        return $this->redirectToRoute('dydaps_configurable_packs_index');
    }

    public function delete(Request $request, int $id): RedirectResponse
    {
        $this->denyDelete($request);
        $this->assertValidCsrf($request, 'dydaps.configurable_packs.write');
        $this->repository->deletePack($id);
        $this->addFlash('success', $this->t('Pack configuration deleted.', 'Modules.Dydapsconfigurablepacks.Admin'));

        return $this->redirectToRoute('dydaps_configurable_packs_index');
    }

    private function handleForm(Request $request, ?array $pack): Response
    {
        $data = $pack ?: [
            'id_product' => 0,
            'id_shop' => (int) \Context::getContext()->shop->id,
            'active' => false,
            'pack_type' => 'fixed',
            'pricing_method' => 'fixed',
            'fixed_price_tax_excl' => 0,
            'forced_price_tax_excl' => 0,
            'global_discount_percent' => 0,
            'global_discount_amount_tax_excl' => 0,
            'stock_behavior' => 'components',
        ];

        $form = $this->createForm(PackGeneralType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $payload = $form->getData();
            $payload['id_pack'] = (int) ($pack['id_pack'] ?? 0);
            $payload['id_shop'] = (int) ($pack['id_shop'] ?? \Context::getContext()->shop->id);
            $this->repository->savePack($payload);
            $this->addFlash('success', $this->t('Pack configuration saved.', 'Modules.Dydapsconfigurablepacks.Admin'));

            return $this->redirectToRoute('dydaps_configurable_packs_index');
        }

        return $this->render('@Modules/dydapsconfigurablepacks/views/templates/admin/pack_form.html.twig', [
            'layoutTitle' => $pack ? $this->t('Edit pack', 'Modules.Dydapsconfigurablepacks.Admin') : $this->t('Create pack', 'Modules.Dydapsconfigurablepacks.Admin'),
            'active' => $pack ? 'packs' : 'new',
            'form' => $form->createView(),
            'canUpdate' => true,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function getArrayParameter(Request $request, string $bag, string $key): array
    {
        $source = $bag === 'request' ? $request->request : $request->query;
        $all = $source->getIterator()->getArrayCopy();
        $value = $all[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
