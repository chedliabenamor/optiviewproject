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

class ColorCrudController extends AbstractCrudController
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
        return Color::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Colors')
            ->setPageTitle('detail', fn (Color $color) => sprintf('Color: %s', $color->getName()))
            ->setPaginatorPageSize(10) // Number of colors per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            ->overrideTemplate('crud/detail', 'admin/color_detail.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        yield TextField::new('imageFile', 'Image File')->setFormType(VichImageType::class)->onlyOnForms();
        yield ImageField::new('imageName', 'Image')->setBasePath('/uploads/images/colors')->hideOnForm();

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
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveColor')
            ->setCssClass('text-warning')
            ->displayIf(static fn (Color $color) => $color->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreColor')
            ->setCssClass('text-success')
            ->displayIf(static fn (Color $color) => $color->getDeletedAt() !== null);

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
