<?php

namespace App\Controller\Admin;

use App\Entity\Brand;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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

class BrandCrudController extends AbstractCrudController
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
        return Brand::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextEditorField::new('description')->hideOnIndex(),
            AssociationField::new('products')->hideOnForm()
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Brands')
            ->setPageTitle('detail', fn (Brand $brand) => sprintf('Brand: %s', $brand->getName()))
            ->setPaginatorPageSize(10) // Number of brands per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            ->overrideTemplate('crud/detail', 'admin/brand_detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveBrand')
            ->setCssClass('text-warning')
            ->displayIf(static fn (Brand $brand) => $brand->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreBrand')
            ->setCssClass('text-success')
            ->displayIf(static fn (Brand $brand) => $brand->getDeletedAt() !== null);

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

    public function archiveBrand(AdminContext $context): Response
    {
        $brand = $context->getEntity()->getInstance();
        if ($brand instanceof Brand) {
            $brand->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Brand \"%s\" was archived.', $brand->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreBrand(AdminContext $context): Response
    {
        $brand = $context->getEntity()->getInstance();
        if ($brand instanceof Brand) {
            $brand->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Brand \"%s\" was restored.', $brand->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}
