<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariantImage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductVariantImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductVariantImage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        // For the upload form (new/edit)
        // Using Field::new with VichImageType as per memory ea752604-4f48-4cdd-bdae-dec4df108487
        yield Field::new('imageFile', 'Image')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'allow_delete' => true, // Allow removing the image
                'download_uri' => false, // No direct download link from the form
                'image_uri' => false, // Do not try to display image in the form type itself
                'asset_helper' => true,
            ])
            ->onlyOnForms();

        // For displaying the image on index/detail
        yield ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/product_variant_images') // Make sure this matches your vich_uploader config
            ->setUploadDir('public/uploads/product_variant_images') // Only needed if you want EA to handle removal from disk on entity deletion, Vich usually handles this.
            ->hideOnForm(); // Hide on forms as 'imageFile' handles the upload

        yield TextField::new('altText', 'Alt Text');
      
    }
}
