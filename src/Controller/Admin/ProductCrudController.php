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
use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

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
        return $crud
            ->setPageTitle('detail', fn (Product $product) => sprintf('Product: %s', $product->getName()))
            ->overrideTemplate('crud/detail', 'admin/product_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/product/index.html.twig')
            ->overrideTemplate('crud/new', 'admin/product/new.html.twig')
            ->overrideTemplate('crud/edit', 'admin/product/edit.html.twig')
            ->setPaginatorPageSize(10) // Set items per page
            ->setPaginatorRangeSize(3) // Number of pages shown in pagination controls
            ->setDefaultSort(['quantityInStock' => 'ASC']); // Default sorting
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
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
                ->formatValue(function ($value, $entity) {
                    if (!$entity instanceof \App\Entity\Product) {
                        return '';
                    }
                    $status = $entity->getStockStatus();
                    $color = 'secondary';
                    switch ($status) {
                        case 'Out of Stock':
                            $color = 'danger';
                            break;
                        case 'Low Stock':
                            $color = 'warning';
                            break;
                        case 'In Stock':
                            $color = 'success';
                            break;
                        default:
                            $color = 'secondary';
                    }
                    return sprintf('<span class="badge text-white bg-%s">%s</span>', $color, $status);
                })
                ->renderAsHtml()
                ->setSortable(false),

            // Field for uploading the overview image (shown only on forms)
            

            // ...existing code...

            // Multiple images via collection (temporarily commented out for debugging)
            CollectionField::new('productModelImages')
                ->setLabel('Additional Images')
                ->useEntryCrudForm(ProductModelImageCrudController::class) // For how entries are managed in forms
                ->setEntryIsComplex(true) // Recommended when entry type is complex (e.g., with VichImageType)
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex()->setColumns('col-md-6'), // Show on Detail, New, Edit pages

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

    public function configureActions(Actions $actions): Actions
    {
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveProduct')
            ->setCssClass('text-warning')
            ->displayIf(static fn (Product $product) => $product->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreProduct')
            ->setCssClass('text-success')
            ->displayIf(static fn (Product $product) => $product->getDeletedAt() !== null);

        $isArchivedView = $this->requestStack->getCurrentRequest()?->query->get('show') === 'archived';

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->set('show', $isArchivedView ? null : 'archived')
            ->generateUrl();

        $viewArchivedOrActive = Action::new($isArchivedView ? 'viewActive' : 'viewArchived', $isArchivedView ? 'View Active' : 'View Archived')
            ->setCssClass('btn btn-secondary')
            ->linkToUrl($url);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveAction)
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_DETAIL, $archiveAction)
            ->add(Crud::PAGE_DETAIL, $restoreAction)
            ->add(Crud::PAGE_INDEX, $viewArchivedOrActive)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, 'archive', 'restore']);
    }

    public function index(AdminContext $context)
    {
        $brands = $this->brandRepository->findAll();
        $categories = $this->categoryRepository->findAll();
        $colors = $this->colorRepository->findAll();
        $styles = $this->styleRepository->findAll();
        $shapes = $this->shapeRepository->findAll();
        $genres = $this->genreRepository->findAll();

        $response = parent::index($context);
        if ($response instanceof KeyValueStore) {
            $response->set('brands', $brands);
            $response->set('categories', $categories);
            $response->set('colors', $colors);
            $response->set('styles', $styles);
            $response->set('shapes', $shapes);
            $response->set('genres', $genres);
        }
        return $response;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $request = $this->requestStack->getCurrentRequest();
        if ($request?->query->get('show') === 'archived') {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }
        // Filtering logic
        if ($brand = $request->query->get('brand')) {
            $queryBuilder->andWhere('entity.brand = :brand')->setParameter('brand', $brand);
        }
        if ($category = $request->query->get('category')) {
            $queryBuilder->andWhere('entity.category = :category')->setParameter('category', $category);
        }
        if ($color = $request->query->get('color')) {
            $queryBuilder->andWhere(':color MEMBER OF entity.colors')->setParameter('color', $color);
        }
        if ($style = $request->query->get('style')) {
            $queryBuilder->andWhere('entity.style = :style')->setParameter('style', $style);
        }
        if ($shape = $request->query->get('shape')) {
            $queryBuilder->andWhere('entity.shape = :shape')->setParameter('shape', $shape);
        }
        if ($genre = $request->query->get('genre')) {
            $queryBuilder->andWhere('entity.genre = :genre')->setParameter('genre', $genre);
        }
        // Stock status filter
        if ($stockStatus = $request->query->get('stockStatus')) {
            switch ($stockStatus) {
                case 'Out of Stock':
                    $queryBuilder->andWhere('entity.quantityInStock = 0');
                    break;
                case 'Low Stock':
                    $queryBuilder->andWhere('entity.quantityInStock > 0 AND entity.quantityInStock <= 10');
                    break;
                case 'In Stock':
                    $queryBuilder->andWhere('entity.quantityInStock > 10');
                    break;
            }
        }
        return $queryBuilder;
    }

    public function archiveProduct(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $product = $context->getEntity()->getInstance();
        if ($product instanceof Product) {
            $product->setDeletedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Product \"%s\" was archived.', $product->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreProduct(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $product = $context->getEntity()->getInstance();
        if ($product instanceof Product) {
            $product->setDeletedAt(null);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Product \"%s\" was restored.', $product->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addWebpackEncoreEntry('admin');
    }
}