<?php

namespace App\Controller\Account;

use App\Entity\Wishlist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

#[IsGranted('ROLE_USER')]
class WishlistPageController extends AbstractController
{
    #[Route('/profile/wishlist', name: 'profile_wishlist', methods: ['GET'])]
    public function index(EntityManagerInterface $em, UploaderHelper $uploaderHelper): Response
    {
        $user = $this->getUser();
        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['user' => $user]);
        $items = [];
        if ($wishlist) {
            foreach ($wishlist->getWishlistItems() as $wi) {
                $p = $wi->getProduct();
                if (!$p) { continue; }
                $v = method_exists($wi, 'getProductVariant') ? $wi->getProductVariant() : null;
                $items[] = [
                    'productId' => $p->getId(),
                    'variantId' => $v ? $v->getId() : null,
                    'name' => $p->getName(),
                    'variantLabel' => $v && method_exists($v, 'getColor') && $v->getColor() ? $v->getColor()->getName() : null,
                    'image' => $p->getOverviewImage() ? $uploaderHelper->asset($p, 'overviewImageFile') : null,
                    'price' => $v && method_exists($v, 'getPrice') ? $v->getPrice() : $p->getPrice(),
                    'currency' => '€',
                    'url' => '/product/' . $p->getId(),
                ];
            }
        }

        return $this->render('account/wishlist.html.twig', [
            'items' => $items,
        ]);
    }
}
