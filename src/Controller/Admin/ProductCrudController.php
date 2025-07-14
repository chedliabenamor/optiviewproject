<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\Style;
use App\Entity\Shape;
use App\Entity\Genre;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use App\Controller\Admin\ProductVariantCrudController; // Added for product variants
use App\Controller\Admin\ColorCrudController;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ColorRepository;
use App\Repository\StyleRepository;
use App\Repository\ShapeRepository;
use App\Repository\GenreRepository;
// use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class ProductCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private BrandRepository $brandRepository;
    private CategoryRepository $categoryRepository;
    private ColorRepository $colorRepository;
    private StyleRepository $styleRepository;
    private ShapeRepository $shapeRepository;
    private GenreRepository $genreRepository;

    public function __construct(
        RequestStack $requestStack,
        AdminUrlGenerator $adminUrlGenerator,
        BrandRepository $brandRepository,
        CategoryRepository $categoryRepository,
        ColorRepository $colorRepository,
        StyleRepository $styleRepository,
        ShapeRepository $shapeRepository,
        GenreRepository $genreRepository
    ) {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->brandRepository = $brandRepository;
        $this->categoryRepository = $categoryRepository;
        $this->colorRepository = $colorRepository;
        $this->styleRepository = $styleRepository;
        $this->shapeRepository = $shapeRepository;
        $this->genreRepository = $genreRepository;
    }


    public function configureCrud(Crud $crud): Crud
    {
        $request = $this->requestStack->getCurrentRequest();
        $pageSize = $request?->query->getInt('pageSize', 10);
        return $crud
            ->setPageTitle('detail', fn(Product $product) => sprintf('Product: %s', $product->getName()))
            ->overrideTemplate('crud/detail', 'admin/product/product_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/product/index.html.twig')
            ->overrideTemplate('crud/new', 'admin/product/new.html.twig')
            ->overrideTemplate('crud/edit', 'admin/product/edit.html.twig')
            ->setPaginatorPageSize($pageSize)
            ->setPaginatorRangeSize(3)
            ->setDefaultSort(['quantityInStock' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
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
                ->linkToCrudAction('restoreProduct')
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
                ->linkToCrudAction('archiveProduct')
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




    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            ImageField::new('overviewImage', 'Overview Image')
                ->setBasePath('/uploads/products')
                ->setLabel('Overview Image File')
                ->onlyOnIndex(),
            TextField::new('name')->setColumns('col-md-12'),
            TextEditorField::new('description')->hideOnIndex()->setColumns('col-md-12'),
            MoneyField::new('price')->setCurrency('EUR')->setColumns('col-md-6'),
            IntegerField::new('quantityInStock', 'Stock')->setColumns('col-md-6'),
            IntegerField::new('loyaltyPoints', 'Loyalty Points')->setColumns('col-md-6'),

            // Stock Status Field with badge and filter
            TextField::new('stockStatus', 'Stock Status')
                ->onlyOnIndex()
                ->formatValue(function ($value, Product $product) {
                    $status = $product->getStockStatus();
                    $color = match ($status) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'secondary',
                    };
                    return sprintf('<span class="badge text-white bg-%s">%s</span>', $color, $status);
                })
                ->renderAsHtml(),

            CollectionField::new('productModelImages')
                ->setLabel('Additional Images')
                ->useEntryCrudForm(ProductModelImageCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex()->setColumns('col-md-6'),

            // Timestamps - shown only on detail page
            DateTimeField::new('createdAt')->onlyOnDetail(),
            DateTimeField::new('updatedAt')->onlyOnDetail(),

            AssociationField::new('brand')
                ->setCrudController(BrandCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6')
                ->autocomplete(),

            AssociationField::new('category')
                ->setCrudController(CategoryCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6')
                ->autocomplete(),

            AssociationField::new('colors')
                ->setCrudController(ColorCrudController::class)
                ->setFormTypeOption('multiple', true)
                ->setFormTypeOption('by_reference', false)
                ->setColumns('col-md-6')
                ->autocomplete(),

            AssociationField::new('style')
                ->setCrudController(StyleCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6') // Added column setting
                ->autocomplete(),

            AssociationField::new('shape')
                ->setCrudController(ShapeCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6') // Added column setting
                ->autocomplete(),

            AssociationField::new('genre')
                ->setCrudController(GenreCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6') // Added column setting
                ->autocomplete(),

            CollectionField::new('productVariants')
                ->setLabel('Product Variants')
                ->useEntryCrudForm(ProductVariantCrudController::class)
                ->setEntryIsComplex(true) // Good practice for nested forms
                ->setFormTypeOption('by_reference', false) // Crucial for Doctrine collections
                ->hideOnIndex()->setColumns('col-md-12'),
        ];
    }
    //costum filters
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('brand'))
            ->add(EntityFilter::new('category'))
            ->add(EntityFilter::new('shape'))
            ->add(EntityFilter::new('genre'))
            ->add(
                ChoiceFilter::new('stockStatus')
                    ->setChoices([
                        'In Stock' => 'In Stock',
                        'Low Stock' => 'Low Stock',
                        'Out of Stock' => 'Out of Stock',
                    ])
            );
    }


    //Soft delete functionality
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $request = $this->requestStack->getCurrentRequest();
        $isArchivedView = $request?->query->get('show') === 'archived';

        if ($isArchivedView) {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }

        return $queryBuilder;
    }
    // this is extended from AbstractCrudController change the deleteEntity method to soft delete
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityInstance->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', sprintf('Product \"%s\" was archived.', $entityInstance->getName()));
    }
    // Custom actions for archiving and restoring products
    public function archiveProduct(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $product = $context->getEntity()->getInstance();

        if ($product instanceof Product) {
            $product->setDeletedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Product "%s" was archived.', $product->getName()));
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function restoreProduct(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $product = $context->getEntity()->getInstance();

        if ($product instanceof Product) {
            $product->setDeletedAt(null);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Product "%s" was restored.', $product->getName()));
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->set('show', 'archived')
            ->generateUrl());
    }
}
