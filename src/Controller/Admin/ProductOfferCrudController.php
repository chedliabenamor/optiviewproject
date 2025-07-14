<?php

namespace App\Controller\Admin;

use App\Entity\ProductOffer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use App\Form\ProductOfferType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
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

class ProductOfferCrudController extends AbstractCrudController
{
    // Ensure only one getFormType method exists and is inside the class
    public function getFormType(): ?string
    {
        return ProductOfferType::class;
    }
   

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
        return ProductOffer::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('products'),
            AssociationField::new('brands'),
            AssociationField::new('productVariants')
                ->setLabel('Product Variants'),
            TextField::new('name'),
            TextEditorField::new('description')->hideOnIndex(),
            ChoiceField::new('discountType')
                ->setChoices([
                    'Percentage' => ProductOffer::TYPE_PERCENTAGE,
                    'Fixed Amount' => ProductOffer::TYPE_FIXED,
                ]),
            NumberField::new('discountValue'),
            DateTimeField::new('startDate'),
            DateTimeField::new('endDate'),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Product Offers')
            ->setPageTitle('detail', fn (ProductOffer $offer) => sprintf('Offer: %s', $offer->getName()))
            ->setPaginatorPageSize(10) // Number of offers per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            ->overrideTemplate('crud/detail', 'admin/product_offer_detail.html.twig');
    }

    // public function configureAssets(Assets $assets): Assets
    // {
    //     return $assets
    //         ->addJsFile('assets/js/offer_form.js');
    // }

    public function createEditQueryBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): QueryBuilder
    {
        $qb = parent::createEditQueryBuilder($entityDto, $formOptions, $context);
        $qb->leftJoin('entity.products', 'p')->addSelect('p');
        $qb->leftJoin('entity.brands', 'b')->addSelect('b');
        $qb->leftJoin('entity.productVariants', 'pv')->addSelect('pv');
        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        $archiveAction = Action::new('archive', 'Archive', 'fa fa-archive')
            ->linkToCrudAction('archiveOffer')
            ->setCssClass('text-warning')
            ->displayIf(static fn (ProductOffer $offer) => $offer->getDeletedAt() === null);

        $restoreAction = Action::new('restore', 'Restore', 'fa fa-undo')
            ->linkToCrudAction('restoreOffer')
            ->setCssClass('text-success')
            ->displayIf(static fn (ProductOffer $offer) => $offer->getDeletedAt() !== null);

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

    public function archiveOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof ProductOffer) {
            $offer->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Offer \"%s\" was archived.', $offer->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof ProductOffer) {
            $offer->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Offer \"%s\" was restored.', $offer->getName()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }
}
