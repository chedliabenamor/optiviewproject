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
            // IdField::new('id')->hideOnForm(),
            TextField::new('imageFile')->setFormType(VichImageType::class)->onlyOnForms(),
            ImageField::new('imageName')->setBasePath($this->params->get('app.path.category_images'))->hideOnForm()->setLabel('Image'),
            TextField::new('name'),
            AssociationField::new('products')->hideOnForm()
           
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/detail', 'admin/category/category_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/category/index.html.twig')
            ->setPaginatorPageSize(10) 
            ->setPaginatorRangeSize(4)
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
            ->linkToCrudAction('restoreCategory')
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
            ->linkToCrudAction('archiveCategory')
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
