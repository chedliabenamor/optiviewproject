<?php

namespace App\Controller\Api;

use App\Controller\Api\CartApiController; // for reference to structure
use App\Repository\CartRepository;
use App\Service\LoyaltyPointsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/checkout')]
class CheckoutApiController extends AbstractController
{
    public function __construct(private CartRepository $cartRepo, private LoyaltyPointsService $loyalty) {}

    #[Route('/shipping-fee', name: 'api_checkout_shipping_fee', methods: ['GET'])]
    public function shippingFee(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $currency = (string)($request->query->get('currency') ?? 'EUR');
        $deliveryType = (string)($request->query->get('deliveryType') ?? 'Standard');
        $destination = (string)($request->query->get('destination') ?? 'Domestic');
        $requestedPoints = (int)($request->query->get('points') ?? 0);

        // Compute subtotal from cart
        $cart = $this->cartRepo->findOneBy(['user' => $user]);
        $subtotal = 0.0;
        if ($cart) {
            foreach ($cart->getCartItems() as $ci) {
                $qty = (int)$ci->getQuantity();
                $price = (float)$ci->getUnitPrice();
                $subtotal += ($qty * $price);
            }
        }

        $taxPercent = 7.0;
        $taxAmount = round($subtotal * ($taxPercent / 100.0), 2);
        $totalWithTax = $subtotal + $taxAmount;

        // Shipping fee logic mirrors Order::getShippingFee()
        $isEuro = ($currency === 'EUR');
        // Base fee by location
        if ($destination === 'International') {
            $base = $isEuro ? 30.90 : 30.90;
        } else {
            $base = $isEuro ? 20.90 : 20.90;
        }
        // Additional by delivery type
        if ($deliveryType === 'Express') {
            $add = $isEuro ? 15.00 : 15.00;
        } elseif ($deliveryType === 'Same-day') {
            $add = $isEuro ? 20.00 : 20.00;
        } else {
            $add = 0.00;
        }
        $shippingFee = $base + $add;
        if ($deliveryType === 'Standard' && $totalWithTax >= 100) {
            $shippingFee = 0.00; // free shipping over threshold
        }
        $shippingFee = number_format($shippingFee, 2, '.', '');

        $grandTotal = number_format($totalWithTax + (float)$shippingFee, 2, '.', '');

        // Optional points redemption preview
        $appliedPoints = 0;
        $pointsDiscount = '0.00';
        if ($requestedPoints > 0) {
            $balance = (int)($user->getLoyaltyPoints() ?? 0);
            $maxAmount = (float)$grandTotal;
            [$appliedPoints, $pointsDiscount] = (function(int $balance, int $req, float $max) {
                $res = $this->loyalty->calculateRedemption($balance, $req, $max);
                return [$res['appliedPoints'], $res['discount']];
            })($balance, $requestedPoints, $maxAmount);
            $grandTotal = number_format(max(0, (float)$grandTotal - (float)$pointsDiscount), 2, '.', '');
        }

        return new JsonResponse([
            'success' => true,
            'currency' => $currency,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax' => number_format($taxAmount, 2, '.', ''),
            'shippingFee' => $shippingFee,
            'total' => $grandTotal,
            'appliedPoints' => $appliedPoints,
            'pointsDiscount' => $pointsDiscount,
        ]);
    }
}
