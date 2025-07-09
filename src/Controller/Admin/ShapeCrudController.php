<?php

namespace App\Controller\Admin;

use App\Entity\Shape;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Vich\UploaderBundle\Form\Type\VichImageType;
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

class ShapeCrudController extends AbstractCrudController
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
        return Shape::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $imageFile = TextField::new('imageFile')->setFormType(VichImageType::class)->hideOnIndex();
        $imageName = ImageField::new('imageName')->setBasePath('/uploads/shapes')->setLabel('Image')->hideOnForm();

        $fields = [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            AssociationField::new('products')->hideOnForm(),
        ];

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            $fields[] = $imageFile;
        } else {
            $fields[] = $imageName;
        }

        return $fields;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Shapes')
            ->setPageTitle('detail', fn (Shape $shape) => sprintf('Shape: %s', $shape->getName()))
            ->setPaginatorPageSize(10) // Number of shapes per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            ->overrideTemplate('crud/detail', 'admin/shape_detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveShape')
            ->setCssClass('text-warning')
            ->displayIf(static fn (Shape $shape) => $shape->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreShape')
            ->setCssClass('text-success')
            ->displayIf(static fn (Shape $shape) => $shape->getDeletedAt() !== null);

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

    public function archiveShape(AdminContext $context): Response
    {
        $shape = $context->getEntity()->getInstance();
        if ($shape instanceof Shape) {
            $shape->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Shape \"%s\" was archived.', $shape->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreShape(AdminContext $context): Response
    {
        $shape = $context->getEntity()->getInstance();
        if ($shape instanceof Shape) {
            $shape->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Shape \"%s\" was restored.', $shape->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}