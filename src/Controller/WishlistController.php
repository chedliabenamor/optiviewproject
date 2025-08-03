<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @IsGranted("ROLE_USER")
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Wishlist;
use App\Entity\Product;

class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'app_wishlist', methods: ['GET'])]
    public function getWishlist(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['user' => $user]);
        $items = [];
        if ($wishlist) {
            foreach ($wishlist->getProducts() as $product) {
                $items[] = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'image' => $product->getOverviewImage() ? $this->generateUrl('vich_uploader.download', ['objectClass' => 'App\\Entity\\Product', 'fieldName' => 'overviewImageFile', 'id' => $product->getId()]) : null,
                    'price' => $product->getPrice() . ' ' . $product->getCurrency(),
                ];
            }
        }
        return new JsonResponse($items);
    }

    #[Route('/wishlist/add', name: 'wishlist_add', methods: ['POST'])]
    public function addToWishlist(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Bad Request'], 400);
        }
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $data = $request->request->all();
        $product = $em->getRepository(Product::class)->find($data['id'] ?? null);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }
        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['user' => $user]);
        if (!$wishlist) {
            $wishlist = new Wishlist();
            $wishlist->setUser($user);
            $em->persist($wishlist);
        }
        $wishlist->addProduct($product);
        $em->flush();
        // Return updated wishlist
        $items = [];
        foreach ($wishlist->getProducts() as $prod) {
            $items[] = [
                'id' => $prod->getId(),
                'name' => $prod->getName(),
                'image' => $prod->getOverviewImage() ? $this->generateUrl('vich_uploader.download', ['objectClass' => 'App\\Entity\\Product', 'fieldName' => 'overviewImageFile', 'id' => $prod->getId()]) : null,
                'price' => $prod->getPrice() . ' ' . $prod->getCurrency(),
            ];
        }
        return new JsonResponse($items);
    }

    #[Route('/wishlist/remove', name: 'wishlist_remove', methods: ['POST'])]
    public function removeFromWishlist(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Bad Request'], 400);
        }
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $data = $request->request->all();
        $product = $em->getRepository(Product::class)->find($data['id'] ?? null);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }
        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['user' => $user]);
        if ($wishlist && $wishlist->getProducts()->contains($product)) {
            $wishlist->removeProduct($product);
            $em->flush();
        }
        // Return updated wishlist
        $items = [];
        if ($wishlist) {
            foreach ($wishlist->getProducts() as $prod) {
                $items[] = [
                    'id' => $prod->getId(),
                    'name' => $prod->getName(),
                    'image' => $prod->getOverviewImage() ? $this->generateUrl('vich_uploader.download', ['objectClass' => 'App\\Entity\\Product', 'fieldName' => 'overviewImageFile', 'id' => $prod->getId()]) : null,
                    'price' => $prod->getPrice() . ' ' . $prod->getCurrency(),
                ];
            }
        }
        return new JsonResponse($items);
    }
}

