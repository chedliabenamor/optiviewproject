<?php

namespace App\Controller\Admin;

use App\Entity\ProductOffer;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ProductOfferCrudController extends AbstractCrudController
{
    private PaginatorInterface $paginator;
    private ProductRepository $productRepository;
    private AdminUrlGenerator $adminUrlGenerator;
    private RequestStack $requestStack;
    private EntityManagerInterface $entityManager;

    public function __construct(
        PaginatorInterface $paginator,
        ProductRepository $productRepository,
        AdminUrlGenerator $adminUrlGenerator,
        RequestStack $requestStack,
        EntityManagerInterface $entityManager
    ) {
        $this->paginator = $paginator;
        $this->productRepository = $productRepository;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return ProductOffer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Product Offers')
            ->setPageTitle('detail', fn (ProductOffer $offer) => sprintf('Offer: %s', $offer->getName()))
            ->setPaginatorPageSize(10)
            ->setPaginatorRangeSize(4)
            ->overrideTemplate('crud/detail', 'admin/productoffer/product_offer_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/productoffer/index.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // IdField::new('id')->hideOnForm(),
            TextField::new('name')->setColumns('col-md-12'),
            AssociationField::new('products')->setColumns('col-md-6'),
            AssociationField::new('productVariants')->setLabel('Product Variants')->setColumns('col-md-6'),
            AssociationField::new('brands')->setColumns('col-md-6'),
            AssociationField::new('categories')->setLabel('Categories')->setColumns('col-md-6'),
            TextEditorField::new('description')->hideOnIndex()->setColumns('col-md-12'),
            TextField::new('discountValue', 'Discount')
                ->onlyOnIndex()
                ->formatValue(function ($value, $entity) {
                    if (method_exists($entity, 'getDiscountType') && method_exists($entity, 'getDiscountValue')) {
                        $type = $entity->getDiscountType();
                        $val = number_format($entity->getDiscountValue(), 2, '.', ',');
                        return $type === 'percentage' ? ("$val%") : ("€$val");
                    }
                    return $value;
                }),
            NumberField::new('discountValue')->setColumns('col-md-6')->hideOnIndex(),
            ChoiceField::new('discountType')
                ->setChoices([
                    'Percentage' => 'percentage',
                    'Fixed Amount' => 'fixed',
                ])
                ->setColumns('col-md-6')
                ->hideOnIndex(),
            DateTimeField::new('startDate')->setColumns('col-md-6'),
            DateTimeField::new('endDate')->setColumns('col-md-6'),
        ];
    }

    public function detail(AdminContext $context): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $offer = $context->getEntity()->getInstance();
        $queryBuilder = $this->productRepository->findProductsForOfferQueryBuilder($offer->getId());

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10 /*limit per page*/
        );

        return $this->render('admin/productoffer/product_offer_detail.html.twig', [
            'entity' => $context->getEntity(),
            'pagination' => $pagination,
        ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $request = $this->requestStack->getCurrentRequest();
        $isArchivedView = $request?->query->get('show') === 'archived';

        $toggleArchivedAction = Action::new(
            $isArchivedView ? 'viewActive' : 'viewArchived',
            $isArchivedView ? 'View Active' : 'View Archived'
        )
            ->linkToUrl(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->set('show', $isArchivedView ? null : 'archived')
                    ->generateUrl()
            )
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        if ($isArchivedView) {
            $archiveOrRestoreAction = Action::new('restore', 'Restore')
                ->setIcon('fa fa-undo')
                ->setCssClass('btn btn-success btn-sm text-white action-restore')
                ->linkToCrudAction('restoreOffer')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'modal',
                    'data-bs-target' => '#confirmationModal',
                    'data-action' => 'restore'
                ]);
            $archiveOrRestoreActionName = 'restore';
        } else {
            $archiveOrRestoreAction = Action::new('archive', 'Archive')
                ->setIcon('fa fa-archive')
                ->setCssClass('btn btn-warning btn-sm text-white')
                ->linkToCrudAction('archiveoffer')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'modal',
                    'data-bs-target' => '#confirmationModal',
                    'data-action' => 'archive'
                ]);
            $archiveOrRestoreActionName = 'archive';
        }

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                fn(Action $action) =>
                $action->setIcon('fa fa-eye')->setLabel('Show')
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn(Action $action) =>
                $action->setIcon('fa fa-edit')->setLabel('Edit')
            )
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
            ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, $archiveOrRestoreActionName]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if ($this->requestStack->getCurrentRequest()?->query->get('show') === 'archived') {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }
        return $queryBuilder;
    }

    public function archiveOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof ProductOffer) {
            $offer->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Offer "%s" was archived.', $offer->getName()));
        }
        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function restoreOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof ProductOffer) {
            $offer->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Offer "%s" was restored.', $offer->getName()));
        }
        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }
}
