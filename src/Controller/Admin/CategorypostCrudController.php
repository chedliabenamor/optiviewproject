<?php

namespace App\Controller\Admin;

use App\Entity\Categorypost;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class CategorypostCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Categorypost::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            SlugField::new('slug')->setTargetFieldName('name'),
            DateTimeField::new('deletedAt')->hideOnForm(),
            AssociationField::new('posts')->hideOnForm(),
        ];
    }
}
