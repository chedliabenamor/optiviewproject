<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class CommentCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        AdminUrlGenerator $adminUrlGenerator,
        Security $security
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->security = $security;
    }
    public static function getEntityFqcn(): string
    {
        return Comment::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();

        $authorField = AssociationField::new('author')->setColumns('col-md-12');
        if (Crud::PAGE_DETAIL === $pageName) {
            $authorField->setTemplatePath('admin/field/author_detail.html.twig');
        } else {
            $authorField->setTemplatePath('admin/field/author.html.twig');
        }
        yield $authorField;

        yield AssociationField::new('post')->setColumns('col-md-12')->setFormTypeOption('disabled', true);
        // Content should be visible but read-only on forms
      
        yield TextareaField::new('content')->hideOnIndex()->setColumns('col-md-12')->setFormTypeOption('disabled', true);
        yield BooleanField::new('isApproved', 'Approved')->setColumns('col-md-6');
        yield DateTimeField::new('createdAt')->hideOnForm();

        if (Crud::PAGE_NEW === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield AssociationField::new('author')->setFormTypeOption('disabled', 'disabled');
        }
    }

    public function createEntity(string $entityFqcn)
    {
        $comment = new Comment();
        $comment->setAuthor($this->security->getUser());
        $comment->setCreatedAt(new \DateTimeImmutable());
        return $comment;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/index', 'admin/comment/index.html.twig')
            ->overrideTemplate('crud/detail', 'admin/comment/detail.html.twig')
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(10)
            ->setPaginatorRangeSize(4);
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
                ->linkToCrudAction('restoreComment')
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
                ->linkToCrudAction('archiveComment')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'modal',
                    'data-bs-target' => '#confirmationModal',
                    'data-action' => 'archive'
                ]);
            $archiveOrRestoreActionName = 'archive';
        }

        $approveAction = Action::new('approve', 'Approve')
            ->setIcon('fa fa-check')
            ->setCssClass('btn btn-success btn-sm text-white')
            ->linkToCrudAction('approveComment')
            ->displayIf(fn($entity) => !$entity->isApproved());

        $unapproveAction = Action::new('unapprove', 'Unapprove')
            ->setIcon('fa fa-times')
            ->setCssClass('btn btn-danger btn-sm text-white')
            ->linkToCrudAction('unapproveComment')
            ->displayIf(fn($entity) => $entity->isApproved());

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

    public function archiveComment(AdminContext $context): Response
    {
        $comment = $context->getEntity()->getInstance();
        if ($comment instanceof Comment) {
            $comment->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Comment was archived.');
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function restoreComment(AdminContext $context): Response
    {
        $comment = $context->getEntity()->getInstance();
        if ($comment instanceof Comment) {
            $comment->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', 'Comment was restored.');
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isApproved', 'Approval Status'));
    }
}
