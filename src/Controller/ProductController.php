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
        $perPage = 15;
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
        $sale = $request->query->getString('sale', ''); // '', 'on', 'off'

        // Define today window for active offers
        $todayStart = new \DateTimeImmutable('today');
        $todayEnd = (new \DateTimeImmutable('tomorrow'))->modify('-1 second');

        // Build COUNT query with filters (exclude archived/soft-deleted everywhere)
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
            ->select('COUNT(DISTINCT p.id)')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('(cat IS NULL OR cat.deletedAt IS NULL)')
            ->andWhere('(br IS NULL OR br.deletedAt IS NULL)')
            ->andWhere('(sh IS NULL OR sh.deletedAt IS NULL)')
            ->andWhere('(st IS NULL OR st.deletedAt IS NULL)')
            ->andWhere('(ge IS NULL OR ge.deletedAt IS NULL)')
            ->andWhere('(pv.id IS NULL OR (pv.deletedAt IS NULL AND pv.isActive = 1))');

        // Filter: In Sale / Not In Sale
        if ($sale === 'on' || $sale === 'off') {
            $existsDql = "EXISTS (
                SELECT po1.id FROM App\\Entity\\ProductOffer po1
                LEFT JOIN po1.products pp
                LEFT JOIN po1.brands pb
                LEFT JOIN po1.categories pc
                LEFT JOIN po1.productVariants pv1
                LEFT JOIN pv1.product pv1p
                WHERE po1.isActive = 1
                  AND po1.deletedAt IS NULL
                  AND po1.startDate <= :todayEnd
                  AND po1.endDate >= :todayStart
                  AND (
                        pp = p
                     OR (br IS NOT NULL AND pb = br)
                     OR (cat IS NOT NULL AND pc = cat)
                     OR pv1p = p
                  )
            )";
            if ($sale === 'on') { $countQb->andWhere($existsDql); }
            else { $countQb->andWhere('NOT ' . $existsDql); }
            $countQb->setParameter('todayStart', $todayStart)->setParameter('todayEnd', $todayEnd);
        }

        if (!empty($catIds)) {
            $countQb->andWhere('cat.id IN (:cats)')->setParameter('cats', $catIds);
        }
        if (!empty($brandIds)) {
            $countQb->andWhere('br.id IN (:brands)')->setParameter('brands', $brandIds);
        }
        if (!empty($colorIds)) {
            // A product matches if ANY of its non-archived variant colors is selected
            $countQb->andWhere('vcol.id IN (:colors) AND vcol.deletedAt IS NULL')->setParameter('colors', $colorIds);
        }
        if (!empty($shapeIds)) {
            $countQb->andWhere('sh.id IN (:shapes)')->setParameter('shapes', $shapeIds);
        }
        if (!empty($styleIds)) {
            $countQb->andWhere('st.id IN (:styles)')->setParameter('styles', $styleIds);
        }
        if (!empty($genreIds)) {
            // Genre at variant level (non-archived only)
            $countQb->andWhere('vgen.id IN (:genres) AND vgen.deletedAt IS NULL')->setParameter('genres', $genreIds);
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

        // Fetch current page products with filters (exclude archived/soft-deleted everywhere)
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
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('(cat IS NULL OR cat.deletedAt IS NULL)')
            ->andWhere('(br IS NULL OR br.deletedAt IS NULL)')
            ->andWhere('(sh IS NULL OR sh.deletedAt IS NULL)')
            ->andWhere('(st IS NULL OR st.deletedAt IS NULL)')
            ->andWhere('(ge IS NULL OR ge.deletedAt IS NULL)')
            ->andWhere('(pv.id IS NULL OR (pv.deletedAt IS NULL AND pv.isActive = 1))')
            ->setFirstResult($offset)
            ->setMaxResults($perPage);

        // Filter: In Sale / Not In Sale
        if ($sale === 'on' || $sale === 'off') {
            $existsDql = "EXISTS (
                SELECT po1.id FROM App\\Entity\\ProductOffer po1
                LEFT JOIN po1.products pp
                LEFT JOIN po1.brands pb
                LEFT JOIN po1.categories pc
                LEFT JOIN po1.productVariants pv1
                LEFT JOIN pv1.product pv1p
                WHERE po1.isActive = 1
                  AND po1.deletedAt IS NULL
                  AND po1.startDate <= :todayEnd
                  AND po1.endDate >= :todayStart
                  AND (
                        pp = p
                     OR (br IS NOT NULL AND pb = br)
                     OR (cat IS NOT NULL AND pc = cat)
                     OR pv1p = p
                  )
            )";
            if ($sale === 'on') { $qb->andWhere($existsDql); }
            else { $qb->andWhere('NOT ' . $existsDql); }
            $qb->setParameter('todayStart', $todayStart)->setParameter('todayEnd', $todayEnd);
        }

        if (!empty($catIds)) {
            $qb->andWhere('cat.id IN (:cats)')->setParameter('cats', $catIds);
        }
        if (!empty($brandIds)) {
            $qb->andWhere('br.id IN (:brands)')->setParameter('brands', $brandIds);
        }
        if (!empty($colorIds)) {
            // Match via non-archived variant colors
            $qb->andWhere('vcol.id IN (:colors) AND vcol.deletedAt IS NULL')->setParameter('colors', $colorIds);
        }
        if (!empty($shapeIds)) {
            $qb->andWhere('sh.id IN (:shapes)')->setParameter('shapes', $shapeIds);
        }
        if (!empty($styleIds)) {
            $qb->andWhere('st.id IN (:styles)')->setParameter('styles', $styleIds);
        }
        if (!empty($genreIds)) {
            // Match via non-archived variant genres
            $qb->andWhere('vgen.id IN (:genres) AND vgen.deletedAt IS NULL')->setParameter('genres', $genreIds);
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
            case 'sale_high': {
                // Order by maximum effective discount percentage among applicable active offers
                $discountExpr = "(SELECT MAX(CASE WHEN po2.discountType = :type_percentage THEN po2.discountValue ELSE (CASE WHEN p.price > 0 THEN (po2.discountValue * 100.0) / p.price ELSE 0 END) END)
                                   FROM App\\Entity\\ProductOffer po2
                                   LEFT JOIN po2.products p2
                                   LEFT JOIN po2.brands b2
                                   LEFT JOIN po2.categories c2
                                   LEFT JOIN po2.productVariants pv2
                                   LEFT JOIN pv2.product pv2p
                                   WHERE po2.isActive = 1 AND po2.deletedAt IS NULL
                                     AND po2.startDate <= :todayEnd AND po2.endDate >= :todayStart
                                     AND (p2 = p OR (br IS NOT NULL AND b2 = br) OR (cat IS NOT NULL AND c2 = cat) OR pv2p = p))";
                $qb->addSelect($discountExpr . ' AS HIDDEN saleDiscountPct');
                $qb->orderBy('saleDiscountPct', 'DESC')->addOrderBy('p.createdAt', 'DESC');
                $qb->setParameter('type_percentage', \App\Entity\ProductOffer::TYPE_PERCENTAGE)
                   ->setParameter('type_fixed', \App\Entity\ProductOffer::TYPE_FIXED)
                   ->setParameter('todayStart', $todayStart)
                   ->setParameter('todayEnd', $todayEnd);
                break;
            }
            case 'sale_low': {
                $discountExpr = "(SELECT MAX(CASE WHEN po2.discountType = :type_percentage THEN po2.discountValue ELSE (CASE WHEN p.price > 0 THEN (po2.discountValue * 100.0) / p.price ELSE 0 END) END)
                                   FROM App\\Entity\\ProductOffer po2
                                   LEFT JOIN po2.products p2
                                   LEFT JOIN po2.brands b2
                                   LEFT JOIN po2.categories c2
                                   LEFT JOIN po2.productVariants pv2
                                   LEFT JOIN pv2.product pv2p
                                   WHERE po2.isActive = 1 AND po2.deletedAt IS NULL
                                     AND po2.startDate <= :todayEnd AND po2.endDate >= :todayStart
                                     AND (p2 = p OR (br IS NOT NULL AND b2 = br) OR (cat IS NOT NULL AND c2 = cat) OR pv2p = p))";
                $qb->addSelect($discountExpr . ' AS HIDDEN saleDiscountPct');
                $qb->orderBy('saleDiscountPct', 'ASC')->addOrderBy('p.createdAt', 'DESC');
                $qb->setParameter('type_percentage', \App\Entity\ProductOffer::TYPE_PERCENTAGE)
                   ->setParameter('type_fixed', \App\Entity\ProductOffer::TYPE_FIXED)
                   ->setParameter('todayStart', $todayStart)
                   ->setParameter('todayEnd', $todayEnd);
                break;
            }
            case 'oldest':
                $qb->orderBy('p.createdAt', 'ASC');
                break;
            case 'newest':
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }

        $products = $qb->getQuery()->getResult();

        // Filter data (facet lists) -> exclude archived
        $categories = $categoryRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $brands     = $brandRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $colors     = $colorRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $shapes     = $shapeRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $genres     = $genreRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
        $styles     = $styleRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);

        // Compute DB min/max price for slider bounds
        $bounds = $productRepository->createQueryBuilder('pp')
            ->select('MIN(pp.price) AS minPrice, MAX(pp.price) AS maxPrice')
            ->andWhere('pp.deletedAt IS NULL')
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
        // Increment product views on GET requests
        if ($request->isMethod('GET') && method_exists($product, 'incrementViews')) {
            $product->incrementViews();
            $em->flush();
        }

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

        // Fetch approved, non-archived reviews for display
        $reviews = $reviewRepository->findBy([
            'product' => $product,
            'isApproved' => true,
            'deletedAt' => null,
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
