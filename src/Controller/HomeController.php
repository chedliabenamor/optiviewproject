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

        return $this->render('pages/index.html.twig', [
            'categories' => $categories,
            'products' => $products,
            'brands' => $brands,
            'colors' => $colors,
            'shapes' => $shapes,
            'genres' => $genres,
        ]);
    }
}
