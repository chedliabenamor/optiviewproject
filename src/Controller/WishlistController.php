<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @IsGranted("ROLE_USER")
 */
class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'app_wishlist')]
    public function index(): Response
    {
        // In a real application, you would fetch the user's wishlist items here.
        // For now, we'll just pass an empty array or some dummy data.
        $wishlistItems = []; 

        return $this->render('wishlist/index.html.twig', [
            'wishlistItems' => $wishlistItems,
        ]);
    }
}
