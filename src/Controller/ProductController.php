<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Review;
use App\Form\ReviewFormType;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Repository\ColorRepository;
use App\Repository\ShapeRepository;
use App\Repository\GenreRepository;
use App\Repository\StyleRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    #[Route('/shop', name: 'product_shop', methods: ['GET'])]
    public function shop(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        BrandRepository $brandRepository,
        ColorRepository $colorRepository,
        ShapeRepository $shapeRepository,
        GenreRepository $genreRepository,
        StyleRepository $styleRepository
    ): Response {
        $perPage = 8;
        $page = max(1, (int) $request->query->get('page', 1));

        // Read filters from query string
        $catIds = $request->query->all('category'); // expects arrays like category[]=1
        $brandIds = $request->query->all('brand');   // expects arrays like brand[]=1
        $colorIds = $request->query->all('color');   // expects arrays like color[]=1
        $shapeIds = $request->query->all('shape');   // expects arrays like shape[]=1
        $styleIds = $request->query->all('style');   // expects arrays like style[]=1
        $genreIds = $request->query->all('genre');   // expects arrays like genre[]=1
        $minPrice = $request->query->getString('minPrice', '');
        $maxPrice = $request->query->getString('maxPrice', '');
        $q = trim($request->query->getString('q', ''));
        $sort = $request->query->getString('sort', 'newest');

        // Build COUNT query with filters
        $countQb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'cat')
            ->leftJoin('p.brand', 'br')
            ->leftJoin('p.colors', 'col')
            ->leftJoin('p.shape', 'sh')
            ->leftJoin('p.style', 'st')
            ->leftJoin('p.genre', 'ge')
            ->leftJoin('p.productVariants', 'pv')
            ->leftJoin('pv.color', 'vcol')
            ->leftJoin('pv.genre', 'vgen')
            ->select('COUNT(DISTINCT p.id)');

        if (!empty($catIds)) {
            $countQb->andWhere('cat.id IN (:cats)')->setParameter('cats', $catIds);
        }
        if (!empty($brandIds)) {
            $countQb->andWhere('br.id IN (:brands)')->setParameter('brands', $brandIds);
        }
        if (!empty($colorIds)) {
            // A product matches if ANY of its variants has one of the selected colors
            $countQb->andWhere('vcol.id IN (:colors)')->setParameter('colors', $colorIds);
        }
        if (!empty($shapeIds)) {
            $countQb->andWhere('sh.id IN (:shapes)')->setParameter('shapes', $shapeIds);
        }
        if (!empty($styleIds)) {
            $countQb->andWhere('st.id IN (:styles)')->setParameter('styles', $styleIds);
        }
        if (!empty($genreIds)) {
            // Genre at variant level
            $countQb->andWhere('vgen.id IN (:genres)')->setParameter('genres', $genreIds);
        }
        if ($minPrice !== '') {
            $countQb->andWhere('p.price >= :minPrice')->setParameter('minPrice', (float) $minPrice);
        }
        if ($maxPrice !== '') {
            $countQb->andWhere('p.price <= :maxPrice')->setParameter('maxPrice', (float) $maxPrice);
        }
        if ($q !== '') {
            $countQb->andWhere('(LOWER(p.name) LIKE :kw OR LOWER(p.description) LIKE :kw)')
                ->setParameter('kw', '%' . strtolower($q) . '%');
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;

        // Fetch current page products with filters
        $qb = $productRepository->createQueryBuilder('p')
            ->select('DISTINCT p')
            ->leftJoin('p.category', 'cat')->addSelect('cat')
            ->leftJoin('p.brand', 'br')->addSelect('br')
            ->leftJoin('p.shape', 'sh')->addSelect('sh')
            ->leftJoin('p.style', 'st')->addSelect('st')
            ->leftJoin('p.genre', 'ge')->addSelect('ge')
            // joins needed for filtering, but we don't addSelect to avoid row explosion
            ->leftJoin('p.colors', 'col')
            ->leftJoin('p.productVariants', 'pv')
            ->leftJoin('pv.color', 'vcol')
            ->leftJoin('pv.genre', 'vgen')
            ->setFirstResult($offset)
            ->setMaxResults($perPage);

        if (!empty($catIds)) {
            $qb->andWhere('cat.id IN (:cats)')->setParameter('cats', $catIds);
        }
        if (!empty($brandIds)) {
            $qb->andWhere('br.id IN (:brands)')->setParameter('brands', $brandIds);
        }
        if (!empty($colorIds)) {
            // Match via variant colors
            $qb->andWhere('vcol.id IN (:colors)')->setParameter('colors', $colorIds);
        }
        if (!empty($shapeIds)) {
            $qb->andWhere('sh.id IN (:shapes)')->setParameter('shapes', $shapeIds);
        }
        if (!empty($styleIds)) {
            $qb->andWhere('st.id IN (:styles)')->setParameter('styles', $styleIds);
        }
        if (!empty($genreIds)) {
            // Match via variant genres
            $qb->andWhere('vgen.id IN (:genres)')->setParameter('genres', $genreIds);
        }
        if ($minPrice !== '') {
            $qb->andWhere('p.price >= :minPrice')->setParameter('minPrice', (float) $minPrice);
        }
        if ($maxPrice !== '') {
            $qb->andWhere('p.price <= :maxPrice')->setParameter('maxPrice', (float) $maxPrice);
        }
        if ($q !== '') {
            $qb->andWhere('(LOWER(p.name) LIKE :kw OR LOWER(p.description) LIKE :kw)')
               ->setParameter('kw', '%' . strtolower($q) . '%');
        }

        // Sorting
        switch ($sort) {
            case 'price_asc':
                $qb->orderBy('p.price', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('p.price', 'DESC');
                break;
            case 'oldest':
                $qb->orderBy('p.createdAt', 'ASC');
                break;
            case 'newest':
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }

        $products = $qb->getQuery()->getResult();

        // Filter data
        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);
        $brands = $brandRepository->findBy([], ['name' => 'ASC']);
        $colors = $colorRepository->findAll();
        $shapes = $shapeRepository->findAll();
        $genres = $genreRepository->findAll();
        $styles = $styleRepository->findBy([], ['name' => 'ASC']);

        // Compute DB min/max price for slider bounds
        $bounds = $productRepository->createQueryBuilder('pp')
            ->select('MIN(pp.price) AS minPrice, MAX(pp.price) AS maxPrice')
            ->getQuery()->getSingleResult();
        $minDb = isset($bounds['minPrice']) ? (float) $bounds['minPrice'] : 0.0;
        $maxDb = isset($bounds['maxPrice']) ? (float) $bounds['maxPrice'] : 0.0;

        // If this is an AJAX request (live search), return partial HTML
        if ($request->isXmlHttpRequest()) {
            $gridHtml = $this->renderView('partials/products/_product_grid.html.twig', [
                'products' => $products,
                'query' => $request->query->all(),
            ]);
            $paginationHtml = $this->renderView('partials/products/_pagination.html.twig', [
                'pagination' => [
                    'page' => $page,
                    'pages' => $pages,
                    'perPage' => $perPage,
                    'total' => $total,
                    'hasPrev' => $page > 1,
                    'hasNext' => $page < $pages,
                    'prev' => max(1, $page - 1),
                    'next' => min($pages, $page + 1),
                ],
                'query' => $request->query->all(),
            ]);

            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'grid' => $gridHtml,
                'pagination' => $paginationHtml,
                'total' => $total,
            ]);
        }

        return $this->render('pages/product/shop.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'colors' => $colors,
            'shapes' => $shapes,
            'genres' => $genres,
            'styles' => $styles,
            'query' => $request->query->all(),
            'priceBounds' => [ 'min' => $minDb, 'max' => $maxDb ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'perPage' => $perPage,
                'total' => $total,
                'hasPrev' => $page > 1,
                'hasNext' => $page < $pages,
                'prev' => max(1, $page - 1),
                'next' => min($pages, $page + 1),
            ],
            'currentSort' => $sort,
            'currentQuery' => $q,
        ]);
    }

    #[Route('/product/{id}', name: 'product_show', requirements: ['id' => '\\d+'])]
    public function show(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository
    ): Response {
        // Build simple comment-only form
        $form = $this->createForm(ReviewFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Require authentication
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('warning', 'Please log in to submit a review.');
                return $this->redirectToRoute('app_login');
            }

            $data = $form->getData();
            $comment = $data['comment'] ?? null;
            $rating = isset($data['rating']) && $data['rating'] !== '' ? (int) $data['rating'] : null;
            if ($rating !== null) {
                // clamp to 1..5
                if ($rating < 1) { $rating = 1; }
                if ($rating > 5) { $rating = 5; }
            }
            if ($comment) {
                $review = new Review();
                $review->setProduct($product);
                $review->setUser($user);
                $review->setComment($comment);
                if ($rating !== null) { $review->setRating($rating); }
                // default to moderation: require admin approval
                $review->setIsApproved(false);
                $em->persist($review);
                $em->flush();
                $this->addFlash('success', 'Thanks! Your review was submitted and will be visible once approved.');
            }

            return $this->redirectToRoute('product_show', ['id' => $product->getId()]);
        }

        // Fetch approved reviews for display
        $reviews = $reviewRepository->findBy([
            'product' => $product,
            'isApproved' => true,
        ], ['createdAt' => 'DESC']);

        return $this->render('pages/product/show.html.twig', [
            'product' => $product,
            'reviewForm' => $form->createView(),
            'reviews' => $reviews,
        ]);
    }

    #[Route('/api/products/{id}/quick-view', name: 'product_quick_view', methods: ['GET'])]
    public function quickView(Product $product, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->normalize($product, 'json', [
            'groups' => 'product_quick_view',
        ]);

        return new JsonResponse($data);
    }
}
