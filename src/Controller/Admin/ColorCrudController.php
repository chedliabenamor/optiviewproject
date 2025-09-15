<?php

namespace App\Controller\Admin;

use App\Entity\Color;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

use App\Repository\ProductRepository;

class ColorCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    private ProductRepository $productRepository;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager, ProductRepository $productRepository)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
    }

    public static function getEntityFqcn(): string
    {
        return Color::class;
    }

    public function detail(AdminContext $context): Response
    {
        $color = $context->getEntity()->getInstance();
        $activeProducts = $this->productRepository->createQueryBuilder('p')
            ->join('p.colors', 'c')
            ->where('c = :color')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('color', $color)
            ->getQuery()->getResult();
        $archivedProducts = $this->productRepository->createQueryBuilder('p')
            ->join('p.colors', 'c')
            ->where('c = :color')
            ->andWhere('p.deletedAt IS NOT NULL')
            ->setParameter('color', $color)
            ->getQuery()->getResult();

        // Fetch product variants by color
        $productVariantRepo = $this->entityManager->getRepository(\App\Entity\ProductVariant::class);
        $activeVariants = $productVariantRepo->createQueryBuilder('v')
            ->where('v.color = :color')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('color', $color)
            ->getQuery()->getResult();
        $archivedVariants = $productVariantRepo->createQueryBuilder('v')
            ->where('v.color = :color')
            ->andWhere('v.deletedAt IS NOT NULL')
            ->setParameter('color', $color)
            ->getQuery()->getResult();

        return $this->render('admin/color/color_detail.html.twig', [
            'entity' => $context->getEntity(),
            'active_products' => $activeProducts,
            'archived_products' => $archivedProducts,
            'active_variants' => $activeVariants,
            'archived_variants' => $archivedVariants,
        ]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Colors')
            ->setPageTitle('detail', fn (Color $color) => sprintf('Color: %s', $color->getName()))
            ->setPaginatorPageSize(10) // Number of colors per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            ->overrideTemplate('crud/detail', 'admin/color/color_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/color/index.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        // yield IdField::new('id')->hideOnForm();
        yield TextField::new('imageFile', 'Image File')->setFormType(VichImageType::class)->onlyOnForms();
        yield ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/images/colors')
            ->setTemplatePath('admin/field/color_image.html.twig')
            ->hideOnForm();
        yield TextField::new('name');
       

        if (Crud::PAGE_INDEX === $pageName) {
            yield IntegerField::new('productsCount', 'Products')
                ->formatValue(function ($value, $entity) {
                    // Count unique products using this color via productVariants
                    $productIds = [];
                    foreach ($entity->getProductVariants() as $variant) {
                        if ($variant->getProduct()) {
                            $productIds[$variant->getProduct()->getId()] = true;
                        }
                    }
                    return count($productIds);
                })
                ->onlyOnIndex();
        }
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
            ->linkToCrudAction('restoreColor')
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
            ->linkToCrudAction('archiveColor')
            ->setHtmlAttributes([
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#confirmationModal',
                'data-action' => 'archive'
            ]);
        $archiveOrRestoreActionName = 'archive';
    }

    return $actions
        ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $action) =>
            $action->setIcon('fa fa-eye')->setLabel('Show')
        )
        ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) =>
            $action->setIcon('fa fa-edit')->setLabel('Edit')
        )
        ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ->remove(Crud::PAGE_DETAIL, Action::DELETE)
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

    public function archiveColor(AdminContext $context): Response
    {
        $color = $context->getEntity()->getInstance();
        if ($color instanceof Color) {
            $color->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Color \"%s\" was archived.', $color->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreColor(AdminContext $context): Response
    {
        $color = $context->getEntity()->getInstance();
        if ($color instanceof Color) {
            $color->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Color \"%s\" was restored.', $color->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}
