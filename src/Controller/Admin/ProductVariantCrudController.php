<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType; // For variant images
use App\Controller\Admin\ProductVariantImageCrudController; // For ProductVariant images collection


use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class ProductVariantCrudController extends AbstractCrudController
{
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }
    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('detail', fn (ProductVariant $variant) => sprintf('Variant: %s', $variant->getSku() ?: $variant->getColor() ?: ('#' . $variant->getId())))
            ->overrideTemplate('crud/detail', 'admin/product_variant_detail.html.twig');
    }
public function updateEntity(\Doctrine\ORM\EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function afterEntityUpdated(AdminContext $context)
    {
        $variant = $context->getEntity()->getInstance();
        $product = $variant->getProduct();
        if ($product) {
            $url = $this->adminUrlGenerator
                ->setController('App\\Controller\\Admin\\ProductCrudController')
                ->setAction('detail')
                ->setEntityId($product->getId())
                ->generateUrl();
            return new RedirectResponse($url);
        }
        return null;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('color')->setColumns('col-md-4');
        yield AssociationField::new('style')->autocomplete()->setColumns('col-md-4');
        yield AssociationField::new('genre')->autocomplete()->setColumns('col-md-4');

        yield TextField::new('sku')->setColumns('col-md-4');
        yield MoneyField::new('price')->setCurrency('EUR')->setStoredAsCents(false)->setColumns('col-md-4');
        yield ChoiceField::new('currency')
            ->setChoices([
                'Dollar' => 'USD',
                'Euro' => 'EUR',
            ])->setColumns('col-md-4');

        yield NumberField::new('stock')->setColumns('col-md-6');
        yield BooleanField::new('isActive')->setColumns('col-md-6');

        yield CollectionField::new('productVariantImages')
            ->useEntryCrudForm(ProductVariantImageCrudController::class)
            ->setFormTypeOptions([
                'by_reference' => false,
            ])
            ->onlyOnForms()
            ->setColumns('col-md-12');

        // To display existing images on index/detail (if needed, requires custom logic or a simpler representation)
        // For example, a count of images or the first image
        // yield ImageField::new('firstImage.imageName') // This would require a custom getter on ProductVariant
        // ->setBasePath('/uploads/product_variant_images') // Adjust path
        // ->setLabel('First Image')
        // ->onlyOnIndex();
    }
}
