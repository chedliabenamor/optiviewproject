<?php

namespace App\Controller;

use App\Repository\ProductOfferRepository;
use App\Repository\ProductRepository;
use App\Entity\ProductOffer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SalesController extends AbstractController
{
    #[Route('/sales', name: 'sales_index', methods: ['GET'])]
    public function index(ProductOfferRepository $offerRepository, Request $request): Response
    {
        // Consider offers active if they overlap any time today
        $todayStart = new \DateTimeImmutable('today');
        $todayEnd = (new \DateTimeImmutable('tomorrow'))->modify('-1 second');

        $qb = $offerRepository->createQueryBuilder('o')
            ->leftJoin('o.products', 'p')->addSelect('p')
            ->leftJoin('o.productVariants', 'pv')->addSelect('pv')
            ->where('o.isActive = :active')
            ->andWhere('o.endDate >= :todayStart')
            ->andWhere('o.startDate <= :todayEnd')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('active', true)
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd);

        // Basic sorting: endSoon (default), discount, newest
        $sort = $request->query->get('sort');
        switch ($sort) {
            case 'discount':
                $qb->orderBy('o.discountValue', 'DESC');
                break;
            case 'newest':
                $qb->orderBy('o.createdAt', 'DESC');
                break;
            default:
                $qb->orderBy('o.endDate', 'ASC');
        }

        $offers = $qb->getQuery()->getResult();

        return $this->render('pages/sales/index.html.twig', [
            'offers' => $offers,
        ]);
    }

    #[Route('/sales/{id}', name: 'sales_show', methods: ['GET'])]
    public function show(ProductOffer $offer, ProductRepository $productRepository): Response
    {
        // Gather related items
        $products = [];
        // Filter out archived/inactive variants
        $variants = array_values(array_filter($offer->getProductVariants()->toArray(), function($v){
            if (method_exists($v, 'getDeletedAt') && $v->getDeletedAt() !== null) return false;
            if (method_exists($v, 'isActive') && !$v->isActive()) return false;
            // Skip if color/style/genre archived
            if ($v->getColor() && method_exists($v->getColor(), 'getDeletedAt') && $v->getColor()->getDeletedAt() !== null) return false;
            if ($v->getStyle() && method_exists($v->getStyle(), 'getDeletedAt') && $v->getStyle()->getDeletedAt() !== null) return false;
            if ($v->getGenre() && method_exists($v->getGenre(), 'getDeletedAt') && $v->getGenre()->getDeletedAt() !== null) return false;
            return true;
        }));

        $hasDirectProducts = ($offer->getProducts() && $offer->getProducts()->count() > 0);
        $hasBrands = (method_exists($offer, 'getBrands') && $offer->getBrands() && $offer->getBrands()->count() > 0);
        $hasCategories = (method_exists($offer, 'getCategories') && $offer->getCategories() && $offer->getCategories()->count() > 0);
        // $variants is an array after filtering; use count($variants)
        $variantOnly = !$hasDirectProducts && !$hasBrands && !$hasCategories && (is_array($variants) ? count($variants) > 0 : ($variants && $variants->count() > 0));

        // If it's NOT variant-only, collect products from all sources
        if (!$variantOnly) {
            // Direct products
            foreach ($offer->getProducts() as $p) { if ($p->getDeletedAt() === null) { $products[$p->getId()] = $p; } }
            // Products from variants in the offer
            foreach ($variants as $v) { if ($v->getProduct() && $v->getProduct()->getDeletedAt() === null) { $products[$v->getProduct()->getId()] = $v->getProduct(); } }
            // Products by brands
            if ($hasBrands) {
                foreach ($offer->getBrands() as $b) {
                    foreach ($productRepository->findBy(['brand' => $b, 'deletedAt' => null]) as $p) { $products[$p->getId()] = $p; }
                }
            }
            // Products by categories
            if ($hasCategories) {
                foreach ($offer->getCategories() as $c) {
                    foreach ($productRepository->findBy(['category' => $c, 'deletedAt' => null]) as $p) { $products[$p->getId()] = $p; }
                }
            }
        }

        $products = array_values($products);

        return $this->render('pages/sales/show.html.twig', [
            'offer' => $offer,
            'products' => $products,
            'variants' => $variants,
            'variantOnly' => $variantOnly,
        ]);
    }
}
