<?php

namespace App\Controller\Admin;

use App\Entity\Categorypost;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use App\Controller\Admin\PostCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class CategorypostCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Categorypost::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined()
            ->setPageTitle(Crud::PAGE_DETAIL, 'Category Details')
            ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewPostsAction = Action::new('viewPosts', 'View Posts', 'fa fa-list-alt')
            ->linkToUrl(function (Categorypost $category) {
                return $this->adminUrlGenerator
                    ->setController(PostCrudController::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->set('filters[category][value]', $category->getId())
                    ->set('filters[category][comparison]', '=')
                    ->generateUrl();
            });

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
                ->setCssClass('btn btn-success btn-sm text-white')
                ->linkToCrudAction('restoreCategorypost');
            $archiveOrRestoreActionName = 'restore';
        } else {
            $archiveOrRestoreAction = Action::new('archive', 'Archive')
                ->setIcon('fa fa-archive')
                ->setCssClass('btn btn-warning btn-sm text-white')
                ->linkToCrudAction('archiveCategorypost');
            $archiveOrRestoreActionName = 'archive';
        }

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $viewPostsAction)
            ->add(Crud::PAGE_DETAIL, $viewPostsAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $a) => $a->setIcon('fa fa-edit')->setLabel('Edit'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
            ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'viewPosts', Action::EDIT, $archiveOrRestoreActionName])
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm()->hideOnIndex();
        yield TextField::new('name');
        yield AssociationField::new('posts', 'Number of Posts')->onlyOnIndex();
        yield SlugField::new('slug')->setTargetFieldName('name')->hideOnIndex();
    }

    public function archiveCategorypost(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $category = $context->getEntity()->getInstance();

        if ($category instanceof Categorypost) {
            $category->setDeletedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Category "%s" was archived.', $category->getName()));
        }

        return $this->redirect($context->getReferrer() ?? $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function restoreCategorypost(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $category = $context->getEntity()->getInstance();

        if ($category instanceof Categorypost) {
            $category->setDeletedAt(null);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Category "%s" was restored.', $category->getName()));
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
