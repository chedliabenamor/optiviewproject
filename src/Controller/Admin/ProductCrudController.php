<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\Style;
use App\Entity\Shape;
use App\Entity\Genre;
use App\Entity\Color;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Vich\UploaderBundle\Form\Type\VichFileType;
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
use App\Filter\StockStatusFilter;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Repository\ProductVariantRepository;
use App\Service\SkuGeneratorService;
use App\Entity\ProductVariant;

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
    private ProductVariantRepository $productVariantRepository;
    private SkuGeneratorService $skuGeneratorService;

    public function __construct(
        RequestStack $requestStack,
        AdminUrlGenerator $adminUrlGenerator,
        BrandRepository $brandRepository,
        CategoryRepository $categoryRepository,
        ColorRepository $colorRepository,
        StyleRepository $styleRepository,
        ShapeRepository $shapeRepository,
        GenreRepository $genreRepository,
        ProductVariantRepository $productVariantRepository,
        SkuGeneratorService $skuGeneratorService
    ) {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->brandRepository = $brandRepository;
        $this->categoryRepository = $categoryRepository;
        $this->colorRepository = $colorRepository;
        $this->styleRepository = $styleRepository;
        $this->shapeRepository = $shapeRepository;
        $this->genreRepository = $genreRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->skuGeneratorService = $skuGeneratorService;
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
            // ->overrideTemplate('crud/edit', 'admin/product/edit.html.twig')
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
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            ImageField::new('overviewImage', 'Overview Image')
                ->setBasePath('/uploads/products')
                ->setLabel('Overview Image')
                ->onlyOnIndex(),
            
            // VichImageType field for upload functionality in forms
            Field::new('overviewImageFile', 'Upload Overview Image')
                ->setFormType(VichImageType::class)
                ->hideOnIndex()
                ->setColumns('col-md-6'),
            // 3D Overlay file (.glb/.obj/.png)
            Field::new('overlayFile', '3D Overlay File')
                ->setFormType(VichFileType::class)
                ->setHelp('Allowed: .glb, .obj, .png (transparent)')
                ->hideOnIndex()
                ->setColumns('col-md-6'),
            TextField::new('overlayAsset', 'Overlay Asset')
                ->onlyOnIndex()
                ->setHelp('Stored filename'),
            TextField::new('name')->setColumns('col-md-12'),
            TextField::new('sku')->setColumns('col-md-12')->setHelp('SKU will be auto-generated if left empty'),
            TextEditorField::new('description')->hideOnIndex()->setColumns('col-md-12'),
            MoneyField::new('price')->setCurrency('EUR')->setStoredAsCents(false)->setColumns('col-md-6'),
            IntegerField::new('quantityInStock', 'Stock')->setColumns('col-md-6'),
            IntegerField::new('loyaltyPoints', 'Loyalty Points')->setColumns('col-md-6'),


            IntegerField::new('quantityInStock', 'Stock Status')
                ->setSortable(true)
                ->onlyOnIndex()
                ->setTemplatePath('admin/field/stock_status.html.twig'),

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
                ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. Gucci', 'placeholder' => 'e.g. Gucci'])
                ->setFormTypeOption('help_html', true)
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Brand::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('e')
                            ->andWhere('e.deletedAt IS NULL')
                            ->orderBy('e.name', 'ASC');
                    },
                ])
                ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="brand" data-fetch="/admin-ajax/brand/new" data-title="Add Brand"><i class="fa fa-plus me-1"></i> Add Brand</button>')
                ,

                AssociationField::new('category')
                ->setCrudController(CategoryCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6')
                ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. Women', 'placeholder' => 'e.g. Women'])
                ->setFormTypeOption('help_html', true)
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Category::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('e')
                            ->andWhere('e.deletedAt IS NULL')
                            ->orderBy('e.name', 'ASC');
                    },
                ])
                ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="category" data-fetch="/admin-ajax/category/new" data-title="Add Category"><i class="fa fa-plus me-1"></i> Add Category</button>')
                ,

            AssociationField::new('colors')
                ->setCrudController(ColorCrudController::class)
                ->setFormTypeOption('multiple', true)
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex()
                ->setColumns('col-md-6')
                ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. White', 'placeholder' => 'e.g. White'])
                ->setFormTypeOption('help_html', true)
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Color::class,
                    'choice_label' => 'name',
                    'multiple' => true,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('e')
                            ->andWhere('e.deletedAt IS NULL')
                            ->orderBy('e.name', 'ASC');
                    },
                ])
                ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="color" data-fetch="/admin-ajax/color/new" data-title="Add Color"><i class="fa fa-plus me-1"></i> Add Color</button>')
                ,

            AssociationField::new('style')
                ->setCrudController(StyleCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6') // Added column setting
                ->setFormTypeOption('help_html', true)
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Style::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('e')
                            ->andWhere('e.deletedAt IS NULL')
                            ->orderBy('e.name', 'ASC');
                    },
                ])
                ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="style" data-fetch="/admin-ajax/style/new" data-title="Add Style"><i class="fa fa-plus me-1"></i> Add Style</button>')
                ,

            AssociationField::new('shape')
                ->setCrudController(ShapeCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6') // Added column setting
                ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. Square', 'placeholder' => 'e.g. Square'])
                ->setFormTypeOption('help_html', true)
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Shape::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('e')
                            ->andWhere('e.deletedAt IS NULL')
                            ->orderBy('e.name', 'ASC');
                    },
                ])
                ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="shape" data-fetch="/admin-ajax/shape/new" data-title="Add Shape"><i class="fa fa-plus me-1"></i> Add Shape</button>')
                ,

            AssociationField::new('genre')
                ->setCrudController(GenreCrudController::class)
                ->setFormTypeOption('by_reference', true)
                ->setColumns('col-md-6') // Added column setting
                ->setFormTypeOption('help_html', true)
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Genre::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('e')
                            ->andWhere('e.deletedAt IS NULL')
                            ->orderBy('e.name', 'ASC');
                    },
                ])
                ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="genre" data-fetch="/admin-ajax/genre/new" data-title="Add Genre"><i class="fa fa-plus me-1"></i> Add Genre</button>')
                ,

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
            ->add(StockStatusFilter::new('quantityInStock', 'Stock Status'));
    }


    //Soft delete functionality
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);



        $request = $this->requestStack->getCurrentRequest();
        $isArchivedView = $request?->query->get('show') === 'archived';

        if ($isArchivedView) {
            // Archived view: show products explicitly archived OR tied to archived relations
            $queryBuilder
                ->leftJoin('entity.category', 'cat')
                ->leftJoin('entity.brand', 'br')
                ->leftJoin('entity.style', 'st')
                ->leftJoin('entity.shape', 'sh')
                ->leftJoin('entity.genre', 'ge')
                ->andWhere('(
                    entity.deletedAt IS NOT NULL
                    OR (cat IS NOT NULL AND cat.deletedAt IS NOT NULL)
                    OR (br IS NOT NULL AND br.deletedAt IS NOT NULL)
                    OR (st IS NOT NULL AND st.deletedAt IS NOT NULL)
                    OR (sh IS NOT NULL AND sh.deletedAt IS NOT NULL)
                    OR (ge IS NOT NULL AND ge.deletedAt IS NOT NULL)
                )');
        } else {
            // Active view: hide archived products and products tied to archived relations
            $queryBuilder
                ->andWhere('entity.deletedAt IS NULL')
                // join related entities to check their archived status
                ->leftJoin('entity.category', 'cat')
                ->leftJoin('entity.brand', 'br')
                ->leftJoin('entity.style', 'st')
                ->leftJoin('entity.shape', 'sh')
                ->leftJoin('entity.genre', 'ge')
                ->andWhere('(cat IS NULL OR cat.deletedAt IS NULL)')
                ->andWhere('(br IS NULL OR br.deletedAt IS NULL)')
                ->andWhere('(st IS NULL OR st.deletedAt IS NULL)')
                ->andWhere('(sh IS NULL OR sh.deletedAt IS NULL)')
                ->andWhere('(ge IS NULL OR ge.deletedAt IS NULL)');
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

        $referrer = $context->getReferrer();
        if (
            $referrer
        ) {
            return $this->redirect($referrer);
        }
        $category = $product->getCategory();
        if ($category) {
            $categoryDetailUrl = $this->adminUrlGenerator
                ->setController(CategoryCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($category->getId())
                ->generateUrl();
            return $this->redirect($categoryDetailUrl);
        }
        return $this->redirect($this->adminUrlGenerator
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

        $referrer = $context->getReferrer();
        if (
            $referrer
        ) {
            return $this->redirect($referrer);
        }
        $category = $product->getCategory();
        if ($category) {
            $categoryDetailUrl = $this->adminUrlGenerator
                ->setController(CategoryCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($category->getId())
                ->generateUrl();
            return $this->redirect($categoryDetailUrl);
        }
        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }


    // Override detail action to provide paginated/filterable variants
    public function detail(AdminContext $context): Response
    {
        $product = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        // Fetch filters from request for both tabs
        $filters_active = $request->query->all('filters_active');
        $filters_archived = $request->query->all('filters_archived');

        // Check if a filter reset was requested and clear the appropriate filters
        if ($request->query->getBoolean('reset')) {
            $activeTab = $request->query->get('active_tab', 'active');
            if ($activeTab === 'active') {
                $filters_active = [];
            } else {
                $filters_archived = [];
            }
            // No redirect needed; we just render the page with the cleared filters.
        }

        // Fetch all colors from the Color entity for the filter dropdown
        $availableColors = $this->colorRepository->findAll();

        // Active Variants Pagination & Filtering
        $pageActive = $request->query->getInt('page_active', 1);
        $paginatorActive = $this->productVariantRepository->findPaginatedByProduct($product->getId(), $pageActive, 10, $filters_active, false);

        // Archived Variants Pagination & Filtering
        $pageArchived = $request->query->getInt('page_archived', 1);
        $paginatorArchived = $this->productVariantRepository->findPaginatedByProduct($product->getId(), $pageArchived, 10, $filters_archived, true);

        return $this->render('admin/product/product_detail.html.twig', [
            'product' => $product,
            'active_variants' => $paginatorActive['data'],
            'total_active' => $paginatorActive['total'],
            'pages_active' => $paginatorActive['pages'],
            'current_page_active' => $pageActive,
            'archived_variants' => $paginatorArchived['data'],
            'total_archived' => $paginatorArchived['total'],
            'pages_archived' => $paginatorArchived['pages'],
            'current_page_archived' => $pageArchived,
            'filters_active' => $filters_active,
            'filters_archived' => $filters_archived,
            'available_colors' => $availableColors,
        ]);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            // Auto-generate SKU if not provided
            if (empty($entityInstance->getSku())) {
                // We need to persist first to get the ID, then generate SKU
                $entityManager->persist($entityInstance);
                $entityManager->flush();
                
                $sku = $this->skuGeneratorService->generateProductSku($entityInstance);
                $entityInstance->setSku($sku);
                
                // Handle variants created with the product
                $this->generateSkusForProductVariants($entityInstance, $entityManager);
                
                $entityManager->flush();
            } else {
                parent::persistEntity($entityManager, $entityInstance);
                
                // Still handle variants even if product SKU was provided
                $this->generateSkusForProductVariants($entityInstance, $entityManager);
            }
        } else {
            parent::persistEntity($entityManager, $entityInstance);
        }
    }

    private function generateSkusForProductVariants(Product $product, EntityManagerInterface $entityManager): void
    {
        $variantCounter = 1;
        foreach ($product->getProductVariants() as $variant) {
            if (empty($variant->getSku())) {
                // For variants created with product, use a sequential counter instead of ID
                $category = $product->getCategory() ? $this->sanitizeForSku($product->getCategory()->getName()) : 'NOCAT';
                $brand = $product->getBrand() ? $this->sanitizeForSku($product->getBrand()->getName()) : 'NOBRAND';
                
                $baseSku = "REF-{$product->getId()}-{$category}-{$brand}-V{$variantCounter}";
                $uniqueSku = $this->ensureUniqueVariantSku($baseSku, $entityManager);
                
                $variant->setSku($uniqueSku);
                $variantCounter++;
            }
        }
    }

    private function sanitizeForSku(string $input): string
    {
        // Remove accents and special characters, convert to uppercase
        $sanitized = iconv('UTF-8', 'ASCII//TRANSLIT', $input);
        // Remove non-alphanumeric characters and replace with nothing
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $sanitized);
        // Convert to uppercase and limit length
        return strtoupper(substr($sanitized, 0, 10));
    }

    private function ensureUniqueVariantSku(string $baseSku, EntityManagerInterface $entityManager): string
    {
        $repository = $entityManager->getRepository(ProductVariant::class);
        $sku = $baseSku;
        $counter = 1;

        while ($this->variantSkuExists($sku, $entityManager)) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }

    private function variantSkuExists(string $sku, EntityManagerInterface $entityManager): bool
    {
        $repository = $entityManager->getRepository(ProductVariant::class);
        
        $count = $repository->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.sku = :sku')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            // Auto-generate SKU if empty or if category/brand changed
            if (empty($entityInstance->getSku())) {
                $sku = $this->skuGeneratorService->generateProductSku($entityInstance);
                $entityInstance->setSku($sku);
            }
        }
        
        parent::updateEntity($entityManager, $entityInstance);
    }
}
