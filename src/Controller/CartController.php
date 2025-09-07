<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_cart')]
    public function cart(): Response
    {
        return $this->render('cart/index.html.twig');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/checkout', name: 'app_checkout')]
    public function checkout(): Response
    {
        // In a full implementation, you would ensure the user has a non-empty cart,
        // load addresses/payment options, etc. For now render a placeholder.
        return $this->render('checkout/index.html.twig');
    }
}
