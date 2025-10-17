<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Service\LoyaltyPointsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Stripe SDK
use Stripe\Stripe;
use Stripe\PaymentIntent;

#[Route('/api/payments')]
class PaymentApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $cartRepo,
        private LoyaltyPointsService $loyalty,
    ) {}

    private function getStripeSecret(): string
    {
        return $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?? '';
    }

    #[Route('/create-intent', name: 'api_payments_create_intent', methods: ['POST'])]
    public function createIntent(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $currency = strtoupper((string)($payload['currency'] ?? 'EUR'));
        if (!in_array($currency, ['EUR', 'USD'], true)) { $currency = 'EUR'; }

        $shippingAddress = (string)($payload['shippingAddress'] ?? '');
        $billingAddress = (string)($payload['billingAddress'] ?? '');
        $shippingProvider = (string)($payload['shippingProvider'] ?? 'DHL');
        $deliveryType = (string)($payload['deliveryType'] ?? 'Standard');
        $destination = (string)($payload['destination'] ?? 'Domestic');
        $notes = (string)($payload['notes'] ?? '');
        $pointsRequested = (int)($payload['loyaltyPointsToApply'] ?? 0);

        // Load cart
        $cart = $this->cartRepo->findOneBy(['user' => $user]);
        if (!$cart || $cart->getCartItems()->count() === 0) {
            return new JsonResponse(['success' => false, 'message' => 'Cart is empty'], Response::HTTP_BAD_REQUEST);
        }

        // Build the Order in 'pending' state
        $order = new Order();
        $order->setUser($user);
        $order->setCurrency($currency);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setShippingProvider($shippingProvider);
        $order->setDeliveryType($deliveryType);
        $order->setDestination($destination);
        $order->setNotes($notes);
        $order->setPaymentMethod('credit card');
        $order->setPaymentStatus('pending');

        $this->em->persist($order);

        // Move cart items to order items (price already discounted in cart)
        foreach ($cart->getCartItems() as $ci) {
            if (!$ci instanceof CartItem) { continue; }
            $product = $ci->getProduct(); if (!$product) { continue; }
            $variant = method_exists($ci, 'getProductVariant') ? $ci->getProductVariant() : null;

            $oi = new OrderItem();
            $oi->setRelatedOrder($order);
            $oi->setProduct($product);
            if ($variant && method_exists($oi, 'setProductVariant')) { $oi->setProductVariant($variant); }
            $oi->setQuantity((int)$ci->getQuantity());
            $price = $ci->getUnitPrice(); if ($price === null || $price === '') { $price = '0.00'; }
            $oi->setUnitPrice((string)$price);
            $oi->setPointsEarned($oi->calculatePointsEarned());
            $this->em->persist($oi);
            $order->addOrderItem($oi);
        }

        // Totals
        $order->updateTotals();
        // Add shipping fee
        $shippingFee = $order->getShippingFee();
        $order->setTotalAmount(bcadd($order->getTotalAmount() ?? '0.00', $shippingFee, 2));

        // Apply loyalty points (compute but do NOT deduct user balance yet; deduct after successful payment)
        $userBalance = (int)($user->getLoyaltyPoints() ?? 0);
        $gross = (float)$order->getFinalTotal();
        if ($pointsRequested > 0 && $userBalance > 0 && $gross > 0) {
            $calc = $this->loyalty->calculateRedemption($userBalance, $pointsRequested, $gross);
            $order->setAppliedPoints($calc['appliedPoints']);
            $order->setPointsDiscount($calc['discount']);
        }

        $this->em->flush(); // ensure order has an ID

        // Compute final amount (no FX conversion applied elsewhere; mirror summary behavior)
        $finalTotal = (float)$order->getFinalTotal();
        if ($currency === 'USD') {
            // Convert from EUR base to USD when charging in USD
            $finalTotal = (float) $order->getConvertedAmount($finalTotal);
        }
        $amountInMinor = (int) round($finalTotal * 100); // cents

        // Create Stripe PaymentIntent
        $secret = $this->getStripeSecret();
        if (!$secret) {
            return new JsonResponse(['success' => false, 'message' => 'Stripe secret key not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        Stripe::setApiKey($secret);

        $params = [
            'amount' => $amountInMinor,
            'currency' => strtolower($currency),
            'payment_method_types' => ['card'],
            'metadata' => [
                'order_id' => (string)$order->getId(),
                'user_id' => (string)$user->getId(),
            ],
            'description' => sprintf('Order #%d - %s', $order->getId(), $user->getUserIdentifier() ?? 'user'),
            'receipt_email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
        ];

        try {
            /** @var PaymentIntent $pi */
            $pi = PaymentIntent::create($params);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to create payment intent', 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'success' => true,
            'clientSecret' => $pi->client_secret,
            'orderId' => $order->getId(),
        ]);
    }

    #[Route('/confirm', name: 'api_payments_confirm', methods: ['POST'])]
    public function confirmPayment(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $payload = json_decode($request->getContent(), true) ?: [];
        $orderId = (int)($payload['orderId'] ?? 0);
        $paymentIntentId = (string)($payload['paymentIntentId'] ?? '');
        if ($orderId <= 0 || !$paymentIntentId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->em->getRepository(Order::class)->find($orderId);
        if (!$order || $order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $secret = $this->getStripeSecret();
        if (!$secret) {
            return new JsonResponse(['success' => false, 'message' => 'Stripe secret key not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        Stripe::setApiKey($secret);

        try {
            $pi = PaymentIntent::retrieve($paymentIntentId);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid payment intent'], Response::HTTP_BAD_REQUEST);
        }

        if ($pi->status !== 'succeeded') {
            return new JsonResponse(['success' => false, 'message' => 'Payment not completed'], Response::HTTP_BAD_REQUEST);
        }

        // Mark order as paid and store transaction id
        $order->setPaymentStatus('paid');
        $order->setTransactionId($pi->id);
        $order->setStatus(Order::STATUS_PROCESSING);
        // Deduct applied loyalty points from user balance now that payment succeeded
        $used = (int) $order->getAppliedPoints();
        if ($used > 0) {
            $current = (int)($user->getLoyaltyPoints() ?? 0);
            $user->setLoyaltyPoints(max(0, $current - $used));
        }
        $this->em->flush();

        // Clear the cart now that payment succeeded
        $cart = $this->cartRepo->findOneBy(['user' => $user]);
        if ($cart) {
            foreach ($cart->getCartItems() as $ci) {
                $cart->removeCartItem($ci);
                $this->em->remove($ci);
            }
            $this->em->flush();
        }

        return new JsonResponse([
            'success' => true,
            'orderId' => $order->getId(),
            'redirect' => $this->generateUrl('app_checkout_confirmation', ['id' => $order->getId()]),
        ]);
    }
}
