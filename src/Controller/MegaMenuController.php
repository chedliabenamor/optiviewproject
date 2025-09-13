<?php

namespace App\Controller;

use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\GenreRepository;
use App\Repository\ShapeRepository;
use App\Repository\StyleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MegaMenuController extends AbstractController
{
    #[Route('/_fragments/mega-menu', name: 'app_mega_menu', methods: ['GET'])]
    public function menu(
        CategoryRepository $categoryRepository,
        BrandRepository $brandRepository,
        GenreRepository $genreRepository,
        StyleRepository $styleRepository,
        ShapeRepository $shapeRepository
    ): Response {
        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);
        $brands     = $brandRepository->findBy([], ['name' => 'ASC']);
        $genres     = $genreRepository->findBy([], ['name' => 'ASC']);
        $styles     = $styleRepository->findBy([], ['name' => 'ASC']);
        $shapes     = $shapeRepository->findBy([], ['name' => 'ASC']);

        return $this->render('partials/nav/_mega_menu.html.twig', [
            'categories' => $categories,
            'brands' => $brands,
            'genres' => $genres,
            'styles' => $styles,
            'shapes' => $shapes,
        ]);
    }
}
