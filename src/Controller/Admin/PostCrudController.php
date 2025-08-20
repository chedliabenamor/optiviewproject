<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Doctrine\ORM\QueryBuilder;
class PostCrudController extends AbstractCrudController
{
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('category'))
            ->add(EntityFilter::new('tags'));
    }
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Post::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $pageSize = $this->requestStack->getCurrentRequest()?->query->getInt('pageSize', 10);

        return $crud
            ->overrideTemplate('crud/detail', 'admin/post/post_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/post/index.html.twig')
            ->setPaginatorPageSize($pageSize)
            ->setPaginatorRangeSize(3)
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $isArchivedView = $this->requestStack->getCurrentRequest()?->query->get('show') === 'archived';

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
                    ->linkToCrudAction('restorePost')
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
                    ->linkToCrudAction('archivePost')
                    ->setHtmlAttributes([
                        'data-bs-toggle' => 'modal',
                        'data-bs-target' => '#confirmationModal',
                        'data-action' => 'archive'
                    ]);
                $archiveOrRestoreActionName = 'archive';
            }

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $a) => $a->setIcon('fa fa-eye')->setLabel('Show'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $a) => $a->setIcon('fa fa-edit')->setLabel('Edit'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
            ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, $archiveOrRestoreActionName])
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }
 

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            ImageField::new('image')
                ->setBasePath('uploads/posts')
                ->setUploadDir('public/uploads/posts')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setRequired(false)
                ->setColumns('col-md-6'),
            TextField::new('title')->setColumns('col-md-6'),
            SlugField::new('slug')->setTargetFieldName('title')->setColumns('col-md-12')->hideOnIndex(),
            TextEditorField::new('content')->setColumns('col-md-12')->hideOnIndex(),
            AssociationField::new('author')->setColumns('col-md-6'),
            DateTimeField::new('date', 'Published Date')->setColumns('col-md-6'),
            DateTimeField::new('createdAt')->hideOnForm()->hideOnIndex(),
            DateTimeField::new('updatedAt')->hideOnForm()->hideOnIndex(),
            DateTimeField::new('deletedAt')->hideOnForm()->hideOnIndex(),
            AssociationField::new('category')->setColumns('col-md-6'),
            AssociationField::new('tags')->setColumns('col-md-6'),
            AssociationField::new('comments')->hideOnForm(),
        ];
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Post) {
            $entityInstance->setDeletedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Post "%s" was archived.', $entityInstance->getTitle()));
        }
    }

    public function archivePost(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $post = $context->getEntity()->getInstance();

        if ($post instanceof Post) {
            $post->setDeletedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Post "%s" was archived.', $post->getTitle()));
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function restorePost(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $post = $context->getEntity()->getInstance();

        if ($post instanceof Post) {
            $post->setDeletedAt(null);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Post "%s" was restored.', $post->getTitle()));
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $isArchivedView = $this->requestStack->getCurrentRequest()?->query->get('show') === 'archived';

        if ($isArchivedView) {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }

        return $queryBuilder;
    }
}
