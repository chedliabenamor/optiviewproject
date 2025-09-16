<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CategoryRepository $categoryRepository,
        PostRepository $postRepository,
        \App\Repository\ProductRepository $productRepository,
        \App\Repository\BrandRepository $brandRepository,
        \App\Repository\ColorRepository $colorRepository,
        \App\Repository\ShapeRepository $shapeRepository,
        \App\Repository\GenreRepository $genreRepository
    ): Response {
        $categories = $categoryRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $products = $productRepository->findBy(['deletedAt' => null], ['id' => 'DESC']); // latest first, non-archived
        $brands = $brandRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $colors = $colorRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $shapes = $shapeRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $genres = $genreRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        
        // Fetch latest 3 blog posts
        $latestPosts = $postRepository->findBy(
            ['deletedAt' => null], 
            ['createdAt' => 'DESC'], 
            3
        );

        // Fetch tab categories by name
        $bestSellerCategory = $categoryRepository->findOneBy(['name' => 'Best Seller']);
        $featuredCategory   = $categoryRepository->findOneBy(['name' => 'Featured']);
        $saleCategory       = $categoryRepository->findOneBy(['name' => 'Sale']);
        $topRateCategory    = $categoryRepository->findOneBy(['name' => 'Top Rate']);

        // Filter out archived products within each tab category
        $bestSellerProducts = $bestSellerCategory ? array_values(array_filter($bestSellerCategory->getProducts()->toArray(), fn($p) => $p->getDeletedAt() === null)) : [];
        $featuredProducts   = $featuredCategory ? array_values(array_filter($featuredCategory->getProducts()->toArray(), fn($p) => $p->getDeletedAt() === null)) : [];
        $saleProducts       = $saleCategory ? array_values(array_filter($saleCategory->getProducts()->toArray(), fn($p) => $p->getDeletedAt() === null)) : [];
        $topRateProducts    = $topRateCategory ? array_values(array_filter($topRateCategory->getProducts()->toArray(), fn($p) => $p->getDeletedAt() === null)) : [];

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
            'latestPosts' => $latestPosts,
        ]);
    }
}
