<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CategoryRepository $categoryRepository,
        \App\Repository\ProductRepository $productRepository,
        \App\Repository\BrandRepository $brandRepository,
        \App\Repository\ColorRepository $colorRepository,
        \App\Repository\ShapeRepository $shapeRepository,
        \App\Repository\GenreRepository $genreRepository
    ): Response {
        $categories = $categoryRepository->findAll();
        $products = $productRepository->findBy([], ['id' => 'DESC']); // latest first
        $brands = $brandRepository->findAll();
        $colors = $colorRepository->findAll();
        $shapes = $shapeRepository->findAll();
        $genres = $genreRepository->findAll();

        // Fetch tab categories by name
        $bestSellerCategory = $categoryRepository->findOneBy(['name' => 'Best Seller']);
        $featuredCategory   = $categoryRepository->findOneBy(['name' => 'Featured']);
        $saleCategory       = $categoryRepository->findOneBy(['name' => 'Sale']);
        $topRateCategory    = $categoryRepository->findOneBy(['name' => 'Top Rate']);

        $bestSellerProducts = $bestSellerCategory ? $bestSellerCategory->getProducts() : [];
        $featuredProducts   = $featuredCategory ? $featuredCategory->getProducts() : [];
        $saleProducts       = $saleCategory ? $saleCategory->getProducts() : [];
        $topRateProducts    = $topRateCategory ? $topRateCategory->getProducts() : [];

        return $this->render('pages/index.html.twig', [
            'categories' => $categories,
            'products' => $products,
            'brands' => $brands,
            'colors' => $colors,
            'shapes' => $shapes,
            'genres' => $genres,
            'bestSellerProducts' => $bestSellerProducts,
            'featuredProducts' => $featuredProducts,
            'saleProducts' => $saleProducts,
            'topRateProducts' => $topRateProducts,
        ]);
    }
}
