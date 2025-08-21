<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    #[Route('/product/{id}', name: 'product_show', requirements: ['id' => '\\d+'])]
    public function show(Product $product): Response
    {
        return $this->render('pages/product/show.html.twig', [
            'product' => $product,
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
