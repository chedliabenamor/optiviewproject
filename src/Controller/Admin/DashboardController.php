<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\Review;
use App\Entity\Color;
// Add other entities you want to manage here

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{


    #[Route('/admin', name: 'admin_dashboard')] // This is the route our LoginRedirectSubscriber uses
    public function index(): Response
    {
        // Option 1. You can redirect to your CRUD controller if you have one
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(UserCrudController::class)->generateUrl());

        // Option 2. You can render a custom template
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('OptiView Store Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('Back to Site', 'fas fa-undo', 'app_home');

        yield MenuItem::section('Users');
        yield MenuItem::linkToCrud('Customers', 'fas fa-users', User::class)->setController(UserCrudController::class);
        // Add ->setController(UserCrudController::class) if you create a custom CRUD controller

        yield MenuItem::section('Catalog');
        yield MenuItem::linkToCrud('Products', 'fas fa-boxes-stacked', Product::class)->setController(ProductCrudController::class);
        yield MenuItem::linkToCrud('Categories', 'fas fa-tags', Category::class)->setController(CategoryCrudController::class);
        yield MenuItem::linkToCrud('Brands', 'fas fa-building', Brand::class)->setController(BrandCrudController::class);
        yield MenuItem::linkToCrud('Colors', 'fas fa-palette', Color::class)->setController(ColorCrudController::class);
        // Add more catalog entities like Style, Shape, Genre here

        yield MenuItem::linkToCrud('Styles', 'fas fa-paint-brush', \App\Entity\Style::class)->setController(\App\Controller\Admin\StyleCrudController::class);
        yield MenuItem::linkToCrud('Shapes', 'fas fa-shapes', \App\Entity\Shape::class)->setController(\App\Controller\Admin\ShapeCrudController::class);
        yield MenuItem::linkToCrud('Genres', 'fas fa-glasses', \App\Entity\Genre::class)->setController(\App\Controller\Admin\GenreCrudController::class);
        yield MenuItem::linkToCrud('Product Offers', 'fas fa-tags', \App\Entity\ProductOffer::class)->setController(\App\Controller\Admin\ProductOfferCrudController::class);

        yield MenuItem::section('Orders & Reviews');
        yield MenuItem::linkToCrud('Orders', 'fas fa-shopping-cart', Order::class)->setController(OrderCrudController::class);
        yield MenuItem::linkToCrud('Reviews', 'fas fa-star', Review::class);

        // Add more sections and entities as needed
    }
}
