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
            ->setPageTitle('detail', fn (Review $review) => sprintf('Review #%d', $review->getId()))
            ->setPaginatorPageSize(10) // Number of reviews per page
            ->setPaginatorRangeSize(4); // Number of page links to show
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('productVariant','Product')->setColumns('col-md-6');
        yield AssociationField::new('user')->setColumns('col-md-6');
        yield IntegerField::new('rating')
            ->setColumns('col-md-6')
            ->setTemplatePath('admin/fields/rating_stars.html.twig')
            ->onlyOnIndex();
        yield IntegerField::new('rating')
            ->setColumns('col-md-6')
            ->onlyOnForms();
        yield BooleanField::new('isApproved')->setColumns('col-md-6');
        yield TextareaField::new('comment')->hideOnIndex()->setColumns('col-md-12');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveReview')
            ->setCssClass('text-warning')
            ->displayIf(static fn (Review $review) => $review->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreReview')
            ->setCssClass('text-success')
            ->displayIf(static fn (Review $review) => $review->getDeletedAt() !== null);

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