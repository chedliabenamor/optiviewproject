<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\HttpFoundation\Response;
use App\Form\CategoryType;
use Symfony\Component\Routing\Annotation\Route;

class CategoryCrudController extends AbstractCrudController
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $entityManager, RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->params = $params;
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            AssociationField::new('products')->hideOnForm(),
            TextField::new('imageFile')->setFormType(VichImageType::class)->onlyOnForms(),
            ImageField::new('imageName')->setBasePath($this->params->get('app.path.category_images'))->hideOnForm()
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/detail', 'admin/category_detail.html.twig')
            ->setPaginatorPageSize(10) // Set items per page (same as user list)
            ->setPaginatorRangeSize(4); // Number of page links to show
    }

    public function configureActions(Actions $actions): Actions
    {
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveCategory')
            ->setCssClass('text-warning')
            ->displayIf(static fn (Category $category) => $category->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreCategory')
            ->setCssClass('text-success')
            ->displayIf(static fn (Category $category) => $category->getDeletedAt() !== null);

        $isArchivedView = $this->requestStack->getCurrentRequest()?->query->get('show') === 'archived';

        // Button to view archived categories (always visible on index page)
        $viewArchivedUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->set('show', 'archived')
            ->generateUrl();
        $viewArchivedButton = Action::new('viewArchived', 'View Archived Categories', 'fa fa-archive')
            ->setCssClass('btn btn-outline-secondary')
            ->linkToUrl($viewArchivedUrl)
            ->displayAsButton();

        // Button to view active categories (only visible when viewing archived)
        $viewActiveUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->set('show', null)
            ->generateUrl();
        $viewActiveButton = Action::new('viewActive', 'View Active Categories', 'fa fa-list')
            ->setCssClass('btn btn-outline-primary')
            ->linkToUrl($viewActiveUrl)
            ->displayAsButton();

        // Add the "View Archived Categories" and "View Active Categories" as global actions (top right, next to Add Category)
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveAction)
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_DETAIL, $archiveAction)
            ->add(Crud::PAGE_DETAIL, $restoreAction);

        if ($isArchivedView) {
            $actions = $actions
                ->add(Crud::PAGE_INDEX, $viewActiveButton)
                ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, 'archive', 'restore', 'viewActive']);
        } else {
            $actions = $actions
                ->add(Crud::PAGE_INDEX, $viewArchivedButton)
                ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, 'archive', 'restore', 'viewArchived']);
        }

        return $actions;
    }

    // public function new(AdminContext $context)
    // {
    //     $request = $context->getRequest();
    //     $entityDto = $context->getEntity();
    //     $form = $this->createNewForm($entityDto, $context->getCrud()->getNewFormOptions(), $context);
    //     $form->handleRequest($request);
    //
    //     if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
    //         if ($form->isValid()) {
    //             $instance = $form->getData();
    //             $this->entityManager->persist($instance);
    //             $this->entityManager->flush();
    //
    //             return new JsonResponse([
    //                 'success' => true,
    //                 'entity' => [
    //                     'id' => $instance->getId(),
    //                     'name' => (string) $instance,
    //                 ],
    //             ]);
    //         }
    //
    //         // On validation error, re-render the form inside the modal
    //         $template = 'admin/category_new_ajax.html.twig';
    //         $html = $this->renderView($template, [
    //             'form' => $form->createView(),
    //             'entity' => $entityDto,
    //             'pageName' => Crud::PAGE_NEW,
    //             'crud' => $context->getCrud(),
    //         ]);
    //
    //         return new JsonResponse(['success' => false, 'form_html' => $html]);
    //     }
    //
    //     return parent::new($context);
    // }

    #[Route('/admin/category/new-ajax', name: 'admin_category_new_ajax')]
    public function newCategoryAjax(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category, [
            'action' => $this->generateUrl('admin_category_new_ajax'),
            'attr' => ['id' => 'category_new_ajax_form']
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'entity' => [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                ],
            ]);
        }

        // Render the form for the modal
        $html = $this->renderView('admin/category_new_ajax.html.twig', [
            'form' => $form->createView(),
        ]);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'form_html' => $html]);
        }
        return new Response($html);
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

    public function archiveCategory(AdminContext $context): Response
    {
        $category = $context->getEntity()->getInstance();
        if ($category instanceof Category) {
            $category->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Category \"%s\" was archived.', $category->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreCategory(AdminContext $context): Response
    {
        $category = $context->getEntity()->getInstance();
        if ($category instanceof Category) {
            $category->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Category \"%s\" was restored.', $category->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}
