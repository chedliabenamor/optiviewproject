<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[Route(path: '/search', name: 'app_search', methods: ['GET'])]
    public function search(
        Request $request,
        ProductRepository $products,
        BrandRepository $brands,
        CategoryRepository $categories,
        PostRepository $posts
    ): Response
    {
        $q = trim((string)($request->query->get('q') ?? $request->query->get('search') ?? ''));
        $like = '%'.mb_strtolower($q).'%';

        $productResults = [];
        $brandResults = [];
        $categoryResults = [];
        $postResults = [];

        if ($q !== '') {
            // Products: search name + description
            $productResults = $products->createQueryBuilder('p')
                ->leftJoin('p.category', 'pc')->addSelect('pc')
                ->leftJoin('p.brand', 'pb')->addSelect('pb')
                ->where('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(50)
                ->getQuery()->getResult();

            // Brands: search by name
            $brandResults = $brands->createQueryBuilder('b')
                ->where('LOWER(b.name) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(25)
                ->getQuery()->getResult();

            // Categories: search by name
            $categoryResults = $categories->createQueryBuilder('c')
                ->where('LOWER(c.name) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(25)
                ->getQuery()->getResult();

            // Posts: search title + content
            $postResults = $posts->createQueryBuilder('p')
                ->where('LOWER(p.title) LIKE :q OR LOWER(p.content) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(25)
                ->getQuery()->getResult();
        }

        return $this->render('search/results.html.twig', [
            'query' => $q,
            'products' => $productResults,
            'brands' => $brandResults,
            'categories' => $categoryResults,
            'posts' => $postResults,
        ]);
    }
}
