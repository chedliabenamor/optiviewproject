<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Select2ProductController extends AbstractController
{
    #[Route('/select2/product', name: 'select2_product')]
    public function productList(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $term = $request->query->get('q');
        $products = $em->getRepository(Product::class)->createQueryBuilder('p')
            ->where('p.name LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'id' => $product->getId(),
                'text' => $product->getName(),
            ];
        }
        return new JsonResponse(['results' => $results]);
    }

    #[Route('/select2/product-variant', name: 'select2_product_variant_by_product')]
    public function productVariantList(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $term = $request->query->get('q');
        $productId = $request->query->get('product');
        $qb = $em->getRepository(ProductVariant::class)->createQueryBuilder('v');
        if ($productId) {
            $qb->andWhere('v.product = :product')->setParameter('product', $productId);
        }
        if ($term) {
            $qb->andWhere('v.sku LIKE :term')->setParameter('term', '%' . $term . '%');
        }
        $variants = $qb->setMaxResults(20)->getQuery()->getResult();
        $results = [];
        foreach ($variants as $variant) {
            $results[] = [
                'id' => $variant->getId(),
                'text' => $variant->getSku(),
            ];
        }
        return new JsonResponse(['results' => $results]);
    }
}
