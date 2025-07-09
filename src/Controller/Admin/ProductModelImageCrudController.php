<?php

namespace App\Controller\Admin;

use App\Entity\ProductModelImage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductModelImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductModelImage::class;
    }
    
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('product')->hideOnForm();
        yield Field::new('imageFile', 'Image') // Target the Vich UploadableField property
            ->setFormType(VichImageType::class)
            ->setLabel('Image File')
            ->setFormTypeOptions([
                'required' => true, // Or false if an image is optional for ProductModelImage
                'allow_delete' => true,
                'delete_label' => 'Remove current image',
                'download_uri' => true,
                'download_label' => 'Download',
                'asset_helper' => true,
            ]);
        // yield TextField::new('altText')->setLabel('Alt Text')->setRequired(false); // Temporarily commented out
    }
}