<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AdminAsset
{
    public function __toString()
    {
        return 'app';
    }

    public function getJsFiles(string $pageName, ?CrudDto $crudDto = null): array
    {
        $jsFiles = [
            Asset::new('assets/admin/js/admin_custom.js')->defer(),
        ];

        if ($pageName === 'detail' && $crudDto && $crudDto->getEntityFqcn() === 'App\Entity\Product') {
            $jsFiles[] = Asset::new('assets/js/product-detail.js')->defer();
        }

        return $jsFiles;
    }

    public function getCssFiles(string $pageName, ?CrudDto $crudDto = null): array
    {
        return [
            Asset::new('assets/css/admin.css'),
        ];
    }
}
