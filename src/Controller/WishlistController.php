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
use App\Entity\WishlistItem;
use App\Entity\Product;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'app_wishlist', methods: ['GET'])]
    public function getWishlist(EntityManagerInterface $em, UploaderHelper $uploaderHelper): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['user' => $user]);
        $items = [];
        if ($wishlist) {
            foreach ($wishlist->getWishlistItems() as $item) {
                $product = $item->getProduct();
                if (!$product) { continue; }
                $variant = method_exists($item, 'getProductVariant') ? $item->getProductVariant() : null;
                // Prefer variant-specific image if available
                $imageUrl = null;
                if ($variant && method_exists($variant, 'getProductVariantImages')) {
                    $imgs = $variant->getProductVariantImages();
                    if ($imgs && count($imgs) > 0) {
                        $first = $imgs[0];
                        if (method_exists($first, 'getImageFile')) {
                            $imageUrl = $uploaderHelper->asset($first, 'imageFile');
                        }
                    }
                }
                if (!$imageUrl && $product->getOverviewImage()) {
                    $imageUrl = $uploaderHelper->asset($product, 'overviewImageFile');
                }
                $price = $variant && method_exists($variant, 'getPrice') ? $variant->getPrice() : $product->getPrice();
                $items[] = [
                    'id' => $product->getId(),
                    'variantId' => $variant ? $variant->getId() : null,
                    'name' => $product->getName(),
                    'image' => $imageUrl,
                    'price' => $price,
                    'currency' => '€',
                ];
            }
        }
        return new JsonResponse($items);
    }

    #[Route('/wishlist/add', name: 'wishlist_add', methods: ['POST'])]
    public function addToWishlist(Request $request, EntityManagerInterface $em, UploaderHelper $uploaderHelper): JsonResponse
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
        // Create a WishlistItem linking to product (and optionally variant if you expand the endpoint)
        $wishlistItem = new WishlistItem();
        if (method_exists($wishlistItem, 'setProduct')) { $wishlistItem->setProduct($product); }
        if (method_exists($wishlistItem, 'setWishlist')) { $wishlistItem->setWishlist($wishlist); }
        $em->persist($wishlistItem);
        if (method_exists($wishlist, 'addWishlistItem')) { $wishlist->addWishlistItem($wishlistItem); }
        $em->flush();
        // Return updated wishlist
        $items = [];
        foreach ($wishlist->getWishlistItems() as $it) {
            $prod = $it->getProduct();
            if (!$prod) { continue; }
            $variant = method_exists($it, 'getProductVariant') ? $it->getProductVariant() : null;
            // Prefer variant image if available
            $imageUrl = null;
            if ($variant && method_exists($variant, 'getProductVariantImages')) {
                $imgs = $variant->getProductVariantImages();
                if ($imgs && count($imgs) > 0) {
                    $first = $imgs[0];
                    if (method_exists($first, 'getImageFile')) {
                        $imageUrl = $uploaderHelper->asset($first, 'imageFile');
                    }
                }
            }
            if (!$imageUrl && $prod->getOverviewImage()) {
                $imageUrl = $uploaderHelper->asset($prod, 'overviewImageFile');
            }
            $price = $variant && method_exists($variant, 'getPrice') ? $variant->getPrice() : $prod->getPrice();
            $items[] = [
                'id' => $prod->getId(),
                'variantId' => $variant ? $variant->getId() : null,
                'name' => $prod->getName(),
                'image' => $imageUrl,
                'price' => $price,
                'currency' => '€',
            ];
        }
        return new JsonResponse($items);
    }

    #[Route('/wishlist/remove', name: 'wishlist_remove', methods: ['POST'])]
    public function removeFromWishlist(Request $request, EntityManagerInterface $em, UploaderHelper $uploaderHelper): JsonResponse
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
        if ($wishlist) {
            // Find wishlist item referencing this product (no variant context in this route)
            foreach ($wishlist->getWishlistItems() as $it) {
                if ($it->getProduct() === $product) {
                    $em->remove($it);
                }
            }
            $em->flush();
        }
        // Return updated wishlist
        $items = [];
        if ($wishlist) {
            foreach ($wishlist->getWishlistItems() as $it) {
                $prod = $it->getProduct();
                if (!$prod) { continue; }
                $variant = method_exists($it, 'getProductVariant') ? $it->getProductVariant() : null;
                // Prefer variant image if available
                $imageUrl = null;
                if ($variant && method_exists($variant, 'getProductVariantImages')) {
                    $imgs = $variant->getProductVariantImages();
                    if ($imgs && count($imgs) > 0) {
                        $first = $imgs[0];
                        if (method_exists($first, 'getImageFile')) {
                            $imageUrl = $uploaderHelper->asset($first, 'imageFile');
                        }
                    }
                }
                if (!$imageUrl && $prod->getOverviewImage()) {
                    $imageUrl = $uploaderHelper->asset($prod, 'overviewImageFile');
                }
                $price = $variant && method_exists($variant, 'getPrice') ? $variant->getPrice() : $prod->getPrice();
                $items[] = [
                    'id' => $prod->getId(),
                    'variantId' => $variant ? $variant->getId() : null,
                    'name' => $prod->getName(),
                    'image' => $imageUrl,
                    'price' => $price,
                    'currency' => '€',
                ];
            }
        }
        return new JsonResponse($items);
    }
}

