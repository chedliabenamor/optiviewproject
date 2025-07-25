<?php

namespace App\Controller\Admin;

use App\Entity\Style;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
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

class StyleCrudController extends AbstractCrudController
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
        return Style::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            AssociationField::new('products')->hideOnForm(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Styles')
            ->setPageTitle('detail', fn (Style $style) => sprintf('Style: %s', $style->getName()))
            ->setPaginatorPageSize(10) // Number of styles per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            ->overrideTemplate('crud/detail', 'admin/style/style_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/style/index.html.twig')
            ->showEntityActionsInlined();
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
            ->linkToCrudAction('restoreStyle')
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
            ->linkToCrudAction('archiveStyle')
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

    public function archiveStyle(AdminContext $context): Response
    {
        $style = $context->getEntity()->getInstance();
        if ($style instanceof Style) {
            $style->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Style \"%s\" was archived.', $style->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreStyle(AdminContext $context): Response
    {
        $style = $context->getEntity()->getInstance();
        if ($style instanceof Style) {
            $style->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Style \"%s\" was restored.', $style->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}
