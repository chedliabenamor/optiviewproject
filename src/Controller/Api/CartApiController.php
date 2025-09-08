<?php

namespace App\Controller\Api;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductOfferRepository;
use App\Entity\ProductOffer;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/cart')]
class CartApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $cartRepo,
        private ProductRepository $productRepo,
        private UploaderHelper $uploaderHelper,
        private ProductOfferRepository $productOfferRepo,
    ) {}

    #[Route('', name: 'api_cart_get', methods: ['GET'])]
    public function getCart(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $this->getOrCreateCart($user);
        // Reprice existing lines based on current offers (ensures discounted price is shown)
        $changed = false;
        foreach ($cart->getCartItems() as $ci) {
            $product = $ci->getProduct(); if (!$product) { continue; }
            $variant = method_exists($ci, 'getProductVariant') ? $ci->getProductVariant() : null;
            $base = null;
            if ($variant && method_exists($variant, 'getPrice')) { $base = (float)$variant->getPrice(); }
            if ($base === null || $base === 0.0) { $base = (float)(method_exists($product, 'getPrice') ? $product->getPrice() : 0); }
            $newPrice = $base;
            try {
                // Prefer variant-specific offer if repository supports it
                $offer = null;
                if ($variant && method_exists($this->productOfferRepo, 'findCurrentOfferForVariant')) {
                    $offer = $this->productOfferRepo->findCurrentOfferForVariant($variant);
                }
                if (!$offer) {
                    $offer = $this->productOfferRepo->findCurrentOfferForProduct($product);
                }
                if ($offer instanceof ProductOffer) {
                    $type = method_exists($offer, 'getDiscountType') ? $offer->getDiscountType() : null;
                    $val = method_exists($offer, 'getDiscountValue') ? (float)$offer->getDiscountValue() : 0.0;
                    if ($type === ProductOffer::DISCOUNT_TYPE_PERCENTAGE) {
                        $newPrice = max(0, $base - ($base * ($val / 100)));
                    } elseif ($type === ProductOffer::DISCOUNT_TYPE_FIXED) {
                        $newPrice = max(0, $base - $val);
                    }
                }
            } catch (\Throwable $e) { /* noop */ }
            $newPrice = number_format($newPrice, 2, '.', '');
            if ((string)$ci->getUnitPrice() !== (string)$newPrice) {
                $ci->setUnitPrice($newPrice);
                $changed = true;
            }
        }
        if ($changed) { $this->em->flush(); }
        return new JsonResponse($this->serializeCart($cart));
    }

    #[Route('/update', name: 'api_cart_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $payload = json_decode($request->getContent(), true);
        $productId = isset($payload['productId']) ? (int)$payload['productId'] : null;
        $variantId = isset($payload['variantId']) && $payload['variantId'] !== '' ? (int)$payload['variantId'] : null;
        $quantity = isset($payload['quantity']) ? (int)$payload['quantity'] : null;
        if (!$productId || $quantity === null) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        $cart = $this->getOrCreateCart($user);
        $target = null;
        foreach ($cart->getCartItems() as $ci) {
            $matchProduct = $ci->getProduct() && $ci->getProduct()->getId() === $productId;
            $ciVarId = method_exists($ci, 'getProductVariant') && $ci->getProductVariant() ? $ci->getProductVariant()->getId() : null;
            $matchVariant = ($ciVarId === ($variantId ?: null));
            if ($matchProduct && $matchVariant) { $target = $ci; break; }
        }
        if (!$target) {
            return new JsonResponse(['success' => false, 'message' => 'Item not found'], Response::HTTP_NOT_FOUND);
        }

        // if quantity <= 0 -> remove line
        if ($quantity <= 0) {
            $cart->removeCartItem($target);
            $this->em->remove($target);
            $this->em->flush();
            return new JsonResponse(['success' => true, 'cart' => $this->serializeCart($cart)]);
        }

        // Stock check against product/variant
        $product = $target->getProduct();
        $variant = method_exists($target, 'getProductVariant') ? $target->getProductVariant() : null;
        if ($variant) {
            if (method_exists($variant, 'getStock') && $variant->getStock() < $quantity) {
                return new JsonResponse(['success' => false, 'message' => 'Not enough stock for selected variant'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            if (method_exists($product, 'getTotalStock') && $product->getTotalStock() < $quantity) {
                return new JsonResponse(['success' => false, 'message' => 'Not enough stock for this product'], Response::HTTP_BAD_REQUEST);
            }
        }

        $target->setQuantity($quantity);
        $this->em->flush();
        return new JsonResponse(['success' => true, 'cart' => $this->serializeCart($cart)]);
    }

    #[Route('/add', name: 'api_cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $payload = json_decode($request->getContent(), true);
        $productId = isset($payload['productId']) ? (int)$payload['productId'] : null;
        $variantId = isset($payload['variantId']) && $payload['variantId'] !== '' ? (int)$payload['variantId'] : null;
        $qty = isset($payload['quantity']) ? (int)$payload['quantity'] : 1;
        if ($qty < 1) { $qty = 1; }

        $product = $this->productRepo->find($productId);
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }
        $variant = null;
        if ($variantId) {
            $variant = $this->em->getRepository(ProductVariant::class)->find($variantId);
            if (!$variant) {
                return new JsonResponse(['success' => false, 'message' => 'Variant not found'], Response::HTTP_NOT_FOUND);
            }
        }

        // Stock checks
        if ($variant) {
            if (method_exists($variant, 'getStock') && $variant->getStock() < $qty) {
                return new JsonResponse(['success' => false, 'message' => 'Not enough stock for selected variant'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            if (method_exists($product, 'getTotalStock') && $product->getTotalStock() < $qty) {
                return new JsonResponse(['success' => false, 'message' => 'Not enough stock for this product'], Response::HTTP_BAD_REQUEST);
            }
        }

        $cart = $this->getOrCreateCart($user);

        // Try to find existing item by product + variant
        $existing = null;
        foreach ($cart->getCartItems() as $ci) {
            if ($ci->getProduct() && $ci->getProduct()->getId() === $product->getId()) {
                $ciVar = method_exists($ci, 'getProductVariant') ? $ci->getProductVariant() : null;
                $ciVarId = $ciVar ? $ciVar->getId() : null;
                $vId = $variant ? $variant->getId() : null;
                if ($ciVarId === $vId) { $existing = $ci; break; }
            }
        }

        if ($existing) {
            $existing->setQuantity($existing->getQuantity() + $qty);
        } else {
            $item = new CartItem();
            $item->setCart($cart);
            $item->setProduct($product);
            if ($variant && method_exists($item, 'setProductVariant')) { $item->setProductVariant($variant); }
            $item->setQuantity($qty);
            // Determine base price from variant or product
            $basePrice = null;
            if ($variant && method_exists($variant, 'getPrice')) { $basePrice = (float)$variant->getPrice(); }
            if ($basePrice === null || $basePrice === 0.0) { $basePrice = (float)(method_exists($product, 'getPrice') ? $product->getPrice() : 0); }
            // Apply active product offer (percentage or fixed)
            $finalPrice = $basePrice;
            try {
                $offer = $this->productOfferRepo->findCurrentOfferForProduct($product);
                if ($offer instanceof ProductOffer) {
                    $type = method_exists($offer, 'getDiscountType') ? $offer->getDiscountType() : null;
                    $val = method_exists($offer, 'getDiscountValue') ? (float)$offer->getDiscountValue() : 0.0;
                    if ($type === ProductOffer::DISCOUNT_TYPE_PERCENTAGE) {
                        $finalPrice = max(0, $basePrice - ($basePrice * ($val / 100)));
                    } elseif ($type === ProductOffer::DISCOUNT_TYPE_FIXED) {
                        $finalPrice = max(0, $basePrice - $val);
                    }
                }
            } catch (\Throwable $e) { /* noop */ }
            $item->setUnitPrice(number_format($finalPrice, 2, '.', ''));
            $this->em->persist($item);
            $cart->addCartItem($item);
        }

        $this->em->flush();
        return new JsonResponse(['success' => true, 'cart' => $this->serializeCart($cart)]);
    }

    #[Route('/remove', name: 'api_cart_remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $payload = json_decode($request->getContent(), true);
        $productId = isset($payload['productId']) ? (int)$payload['productId'] : null;
        $variantId = isset($payload['variantId']) && $payload['variantId'] !== '' ? (int)$payload['variantId'] : null;
        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        $cart = $this->getOrCreateCart($user);
        foreach ($cart->getCartItems() as $ci) {
            $matchProduct = $ci->getProduct() && $ci->getProduct()->getId() === $productId;
            $ciVarId = method_exists($ci, 'getProductVariant') && $ci->getProductVariant() ? $ci->getProductVariant()->getId() : null;
            $matchVariant = ($ciVarId === ($variantId ?: null));
            if ($matchProduct && $matchVariant) {
                $cart->removeCartItem($ci);
                $this->em->remove($ci);
                break;
            }
        }
        $this->em->flush();
        return new JsonResponse(['success' => true, 'cart' => $this->serializeCart($cart)]);
    }

    #[Route('/merge', name: 'api_cart_merge', methods: ['POST'])]
    public function merge(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $payload = json_decode($request->getContent(), true);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $cart = $this->getOrCreateCart($user);

        foreach ($items as $row) {
            $pid = isset($row['productId']) ? (int)$row['productId'] : null;
            if (!$pid) { continue; }
            $vid = isset($row['variantId']) && $row['variantId'] !== '' ? (int)$row['variantId'] : null;
            $qty = isset($row['quantity']) ? (int)$row['quantity'] : 1;
            if ($qty < 1) { $qty = 1; }

            $product = $this->productRepo->find($pid);
            if (!$product) { continue; }
            $variant = null;
            if ($vid) {
                $variant = $this->em->getRepository(ProductVariant::class)->find($vid);
                if (!$variant) { continue; }
            }

            // Stock check
            if ($variant) {
                if (method_exists($variant, 'getStock') && $variant->getStock() <= 0) { continue; }
            } else {
                if (method_exists($product, 'getTotalStock') && $product->getTotalStock() <= 0) { continue; }
            }

            // Find existing line
            $existing = null;
            foreach ($cart->getCartItems() as $ci) {
                if ($ci->getProduct() && $ci->getProduct()->getId() === $pid) {
                    $ciVar = method_exists($ci, 'getProductVariant') ? $ci->getProductVariant() : null;
                    $ciVarId = $ciVar ? $ciVar->getId() : null;
                    $vId = $variant ? $variant->getId() : null;
                    if ($ciVarId === $vId) { $existing = $ci; break; }
                }
            }
            if ($existing) {
                $existing->setQuantity($existing->getQuantity() + $qty);
            } else {
                // Create new line with discounted unit price if offer active
                $item = new CartItem();
                $item->setCart($cart);
                $item->setProduct($product);
                if ($variant && method_exists($item, 'setProductVariant')) { $item->setProductVariant($variant); }
                $item->setQuantity($qty);

                // Determine base price
                $basePrice = null;
                if ($variant && method_exists($variant, 'getPrice')) { $basePrice = (float)$variant->getPrice(); }
                if ($basePrice === null || $basePrice === 0.0) { $basePrice = (float)(method_exists($product, 'getPrice') ? $product->getPrice() : 0); }

                // Apply active product offer
                $finalPrice = $basePrice;
                try {
                    $offer = $this->productOfferRepo->findCurrentOfferForProduct($product);
                    if ($offer instanceof ProductOffer) {
                        $type = method_exists($offer, 'getDiscountType') ? $offer->getDiscountType() : null;
                        $val = method_exists($offer, 'getDiscountValue') ? (float)$offer->getDiscountValue() : 0.0;
                        if ($type === ProductOffer::DISCOUNT_TYPE_PERCENTAGE) {
                            $finalPrice = max(0, $basePrice - ($basePrice * ($val / 100)));
                        } elseif ($type === ProductOffer::DISCOUNT_TYPE_FIXED) {
                            $finalPrice = max(0, $basePrice - $val);
                        }
                    }
                } catch (\Throwable $e) { /* noop */ }
                $item->setUnitPrice(number_format($finalPrice, 2, '.', ''));

                $this->em->persist($item);
                $cart->addCartItem($item);
            }
        }
        $this->em->flush();
        return new JsonResponse(['success' => true, 'cart' => $this->serializeCart($cart)]);
    }

    private function getOrCreateCart($user): Cart
    {
        $cart = $this->cartRepo->findOneBy(['user' => $user]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->em->persist($cart);
            $this->em->flush();
        }
        return $cart;
    }

    private function serializeCart(Cart $cart): array
    {
        $items = [];
        foreach ($cart->getCartItems() as $ci) {
            $p = $ci->getProduct();
            if (!$p) { continue; }
            $v = method_exists($ci, 'getProductVariant') ? $ci->getProductVariant() : null;
            // Resolve best image
            $imageUrl = null;
            if ($v) {
                $images = method_exists($v, 'getProductVariantImages') ? $v->getProductVariantImages() : null;
                if ($images && count($images) > 0) {
                    $first = $images->first();
                    if ($first) {
                        $imageUrl = $this->uploaderHelper->asset($first, 'imageFile');
                    }
                }
            }
            if (!$imageUrl && method_exists($p, 'getOverviewImage') && $p->getOverviewImage()) {
                $imageUrl = $this->uploaderHelper->asset($p, 'overviewImageFile');
            }
            // Optional: try first product model image as last fallback
            if (!$imageUrl && method_exists($p, 'getProductModelImages')) {
                $pmis = $p->getProductModelImages();
                if ($pmis && count($pmis) > 0) {
                    $imageUrl = $this->uploaderHelper->asset($pmis->first(), 'imageFile');
                }
            }
            $items[] = [
                'id' => $ci->getId(),
                'productId' => $p->getId(),
                'variantId' => $v ? $v->getId() : null,
                'name' => $p->getName(),
                'variantName' => $v && method_exists($v, 'getColor') && $v->getColor() ? $v->getColor()->getName() : '',
                'image' => $imageUrl,
                'unitPrice' => (float)$ci->getUnitPrice(),
                'quantity' => (int)$ci->getQuantity(),
                'subtotal' => (float)$ci->getSubtotal(),
            ];
        }
        return [
            'items' => $items,
            'total' => (float)$cart->getTotal(),
            'currency' => '€',
        ];
    }
}
