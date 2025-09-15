<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class ReviewCrudController extends AbstractCrudController
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
        return Review::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle('index', 'Reviews')
            ->setPageTitle('detail', fn(Review $review) => sprintf('Review #%d', $review->getId()))
            ->setPaginatorPageSize(10) // Number of reviews per page
            ->setPaginatorRangeSize(4)
            ->overrideTemplate('crud/index', 'admin/Review/index.html.twig')
            ->overrideTemplate('crud/detail', 'admin/Review/detail.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        // Show product (product-level reviews)
        yield AssociationField::new('product', 'Product')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->autocomplete()
            ->setFormTypeOption('disabled', true);
        // Display user but do not allow changing from admin forms
        yield AssociationField::new('user')->setColumns('col-md-6')->hideOnForm();
        yield AssociationField::new('user')->setColumns('col-md-6')->onlyOnForms()->setFormTypeOption('disabled', true);
        // Rating: read-only in admin
        yield IntegerField::new('rating')
            ->setColumns('col-md-6')
            ->setTemplatePath('admin/fields/rating_stars.html.twig')
            ->onlyOnIndex();
        yield IntegerField::new('rating')
            ->setColumns('col-md-6')
            ->setTemplatePath('admin/fields/rating_stars.html.twig')
            ->onlyOnDetail();
        yield IntegerField::new('rating')
            ->setColumns('col-md-6')
            ->onlyOnForms()
            ->setFormTypeOption('disabled', true);
        yield BooleanField::new('isApproved')->setColumns('col-md-6');
        yield TextareaField::new('comment')->hideOnIndex()->setColumns('col-md-12')->setFormTypeOption('disabled', true);
        yield DateTimeField::new('createdAt')->hideOnForm();
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
                ->linkToCrudAction('restoreReview')
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
                ->linkToCrudAction('archiveReview')
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
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
            ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, $archiveOrRestoreActionName]);
    }
    public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(
            ChoiceFilter::new('isApproved')
                ->setLabel('Approval Status')
                ->setChoices([
                    'Approved' => true,
                    'Not Approved' => false,
                ])
        );
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

    public function archiveReview(AdminContext $context): Response
    {
        $review = $context->getEntity()->getInstance();
        if ($review instanceof Review) {
            $review->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Review #%d was archived.', $review->getId()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreReview(AdminContext $context): Response
    {
        $review = $context->getEntity()->getInstance();
        if ($review instanceof Review) {
            $review->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Review #%d was restored.', $review->getId()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}
