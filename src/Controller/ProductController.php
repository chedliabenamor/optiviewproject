<?php

namespace App\Controller;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/product/{id}', name: 'product_show', requirements: ['id' => '\\d+'])]
    public function show(Product $product): Response
    {
        return $this->render('pages/product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
