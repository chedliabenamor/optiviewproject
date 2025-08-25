<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Wishlist;
use App\Entity\WishlistItem;
use App\Repository\ProductRepository;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/wishlist')]
class WishlistApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WishlistRepository $wishlistRepository,
        private ProductRepository $productRepository
    ) {
    }

    #[Route('/check/{productId}', name: 'api_wishlist_check', methods: ['GET'])]
    public function checkWishlistStatus(int $productId, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $variantId = $request->query->get('variantId');
        $variant = null;
        
        if ($variantId) {
            $variant = $this->entityManager->getRepository(ProductVariant::class)->find($variantId);
        }

        // Check if user is authenticated
        if ($this->getUser()) {
            $wishlist = $this->getOrCreateWishlist($this->getUser());
            $isInWishlist = $wishlist->hasProduct($product, $variant);
        } else {
            // For guest users, check session
            $session = $request->getSession();
            $guestWishlist = $session->get('guest_wishlist', []);
            $key = $product->getId() . ($variant ? '_' . $variant->getId() : '');
            $isInWishlist = in_array($key, $guestWishlist);
        }

        return new JsonResponse(['inWishlist' => $isInWishlist]);
    }

    #[Route('/toggle/{productId}', name: 'api_wishlist_toggle', methods: ['POST'])]
    public function toggleWishlist(int $productId, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $variantId = $data['variantId'] ?? null;
        $variant = null;
        
        if ($variantId) {
            $variant = $this->entityManager->getRepository(ProductVariant::class)->find($variantId);
        }

        try {
            if ($this->getUser()) {
                // Authenticated user
                $wishlist = $this->getOrCreateWishlist($this->getUser());
                $isInWishlist = $wishlist->hasProduct($product, $variant);
                
                if ($isInWishlist) {
                    // Remove from wishlist
                    foreach ($wishlist->getWishlistItems() as $item) {
                        if ($item->getProduct() === $product && $item->getProductVariant() === $variant) {
                            $wishlist->removeWishlistItem($item);
                            $this->entityManager->remove($item);
                            break;
                        }
                    }
                    $action = 'removed';
                } else {
                    // Add to wishlist
                    $wishlistItem = new WishlistItem();
                    $wishlistItem->setProduct($product);
                    $wishlistItem->setProductVariant($variant);
                    $wishlist->addWishlistItem($wishlistItem);
                    $this->entityManager->persist($wishlistItem);
                    $action = 'added';
                }
                
                $this->entityManager->flush();
            } else {
                // Guest user - use session
                $session = $request->getSession();
                $guestWishlist = $session->get('guest_wishlist', []);
                $key = $product->getId() . ($variant ? '_' . $variant->getId() : '');
                
                if (in_array($key, $guestWishlist)) {
                    // Remove from wishlist
                    $guestWishlist = array_filter($guestWishlist, fn($item) => $item !== $key);
                    $action = 'removed';
                } else {
                    // Add to wishlist
                    $guestWishlist[] = $key;
                    $action = 'added';
                }
                
                $session->set('guest_wishlist', array_values($guestWishlist));
            }

            return new JsonResponse([
                'success' => true,
                'action' => $action,
                'inWishlist' => $action === 'added'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update wishlist'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getOrCreateWishlist($user): Wishlist
    {
        $wishlist = $this->wishlistRepository->findOneBy(['user' => $user]);
        
        if (!$wishlist) {
            $wishlist = new Wishlist();
            $wishlist->setUser($user);
            $this->entityManager->persist($wishlist);
            $this->entityManager->flush();
        }
        
        return $wishlist;
    }
}
