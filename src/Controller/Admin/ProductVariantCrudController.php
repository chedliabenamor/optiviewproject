<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Controller\Admin\ProductVariantImageCrudController;

class ProductVariantCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('detail', fn(ProductVariant $variant) => sprintf('Variant: %s', $variant->getSku() ?: $variant->getColor() ?: ('#' . $variant->getId())))
            ->overrideTemplate('crud/detail', 'admin/productvariant/product_variant_detail.html.twig')
            ->setPaginatorPageSize(10)
            ->setPaginatorRangeSize(4)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('color')->setColumns('col-md-4');
        yield AssociationField::new('style')->autocomplete()->setColumns('col-md-4');
        yield AssociationField::new('genre')->autocomplete()->setColumns('col-md-4');

        yield TextField::new('sku')->setColumns('col-md-4');
        yield MoneyField::new('price')->setCurrency('EUR')->setStoredAsCents(false)->setColumns('col-md-4');
        yield ChoiceField::new('currency')
            ->setChoices([
                'Dollar' => 'USD',
                'Euro' => 'EUR',
            ])->setColumns('col-md-4');

        yield NumberField::new('stock')->setColumns('col-md-6');
        yield BooleanField::new('isActive')->setColumns('col-md-6');

        yield CollectionField::new('productVariantImages')
            ->useEntryCrudForm(ProductVariantImageCrudController::class)
            ->setFormTypeOptions([
                'by_reference' => false,
            ])
            ->onlyOnForms()
            ->setColumns('col-md-12');
    }

        public function configureActions(Actions $actions): Actions
    {
        $archiveVariantAction = Action::new('archiveVariant', 'Archive', 'fa fa-archive')
            ->setCssClass('btn btn-warning btn-sm text-white')
            ->linkToCrudAction('archiveVariant');

        $restoreVariantAction = Action::new('restoreVariant', 'Restore', 'fa fa-undo')
            ->setCssClass('btn btn-success btn-sm text-white')
            ->linkToCrudAction('restoreVariant');

        $request = $this->requestStack->getCurrentRequest();
        $showArchived = $request?->query->get('show') === 'archived';

        $toggleArchivedAction = Action::new(
            $showArchived ? 'viewActive' : 'viewArchived',
            $showArchived ? 'View Active' : 'View Archived'
        )
            ->linkToUrl(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->set('show', $showArchived ? null : 'archived')
                    ->generateUrl()
            )
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        if ($showArchived) {
            $archiveOrRestoreAction = Action::new('restore', 'Restore')
                ->setIcon('fa fa-undo')
                ->setCssClass('btn btn-success btn-sm text-white')
                ->linkToCrudAction('restoreVariant');
            $archiveOrRestoreActionName = 'restore';
        } else {
            $archiveOrRestoreAction = Action::new('archive', 'Archive')
                ->setIcon('fa fa-archive')
                ->setCssClass('btn btn-warning btn-sm text-white')
                ->linkToCrudAction('archiveVariant');
            $archiveOrRestoreActionName = 'archive';
        }

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $action) =>
                $action->setIcon('fa fa-eye')->setLabel('Show'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) =>
                $action->setIcon('fa fa-edit')->setLabel('Edit'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
            ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, $archiveOrRestoreActionName])
            
            ->add(Crud::PAGE_DETAIL, $restoreVariantAction)
            ->add(Crud::PAGE_DETAIL, $archiveVariantAction);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $showArchived = $this->requestStack->getCurrentRequest()?->query->get('show') === 'archived';

        if ($showArchived) {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }

        return $queryBuilder;
    }

        public function archiveVariant(AdminContext $context): Response
    {
        $variant = $context->getEntity()->getInstance();
        if ($variant instanceof ProductVariant) {
            $variant->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Variant "%s" was archived.', $variant->getSku()));
        }

        $url = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($variant->getProduct()->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

        public function restoreVariant(AdminContext $context): Response
    {
        $variant = $context->getEntity()->getInstance();
        if ($variant instanceof ProductVariant) {
            $variant->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Variant "%s" was restored.', $variant->getSku()));
        }

        $url = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($variant->getProduct()->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $variant = $context->getEntity()->getInstance();
        if ($variant instanceof ProductVariant && $variant->getProduct()) {
            $url = $this->adminUrlGenerator
                ->setController(ProductCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($variant->getProduct()->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }
}
