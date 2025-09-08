<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $cartRepo,
    ) {}

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        // Defaults shown in the form
        $defaults = [
            'currency' => 'EUR',
            'deliveryType' => 'Standard',
            'shippingProvider' => 'DHL',
            'destination' => 'Domestic',
            'notes' => '',
            'paymentMethod' => 'credit card',
        ];

        return $this->render('checkout/index.html.twig', [
            'defaults' => $defaults,
        ]);
    }

    #[Route('/checkout/confirm', name: 'app_checkout_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        // CSRF protection
        $token = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('checkout', $token)) {
            $this->addFlash('danger', 'Invalid form submission. Please try again.');
            return $this->redirectToRoute('app_checkout');
        }

        // Collect posted fields
        $currency = (string)($request->request->get('currency') ?? 'EUR');
        $shippingAddress = (string)($request->request->get('shippingAddress') ?? '');
        $billingAddress = (string)($request->request->get('billingAddress') ?? '');
        $shippingProvider = (string)($request->request->get('shippingProvider') ?? 'DHL');
        $deliveryType = (string)($request->request->get('deliveryType') ?? 'Standard');
        $destination = (string)($request->request->get('destination') ?? 'Domestic');
        $notes = (string)($request->request->get('notes') ?? '');
        $paymentMethod = (string)($request->request->get('paymentMethod') ?? 'credit card');

        // Basic validation (lightweight for now)
        $allowedCurrency = ['EUR', 'USD'];
        $allowedProviders = ['DHL', 'UPS', 'Poste', 'GLS'];
        $allowedDelivery = ['Standard', 'Express', 'Same-day'];
        $allowedDestination = ['Domestic', 'International'];
        $allowedPayment = ['paypal', 'credit card'];

        if (!in_array($currency, $allowedCurrency, true)
            || !in_array($shippingProvider, $allowedProviders, true)
            || !in_array($deliveryType, $allowedDelivery, true)
            || !in_array($destination, $allowedDestination, true)
            || !in_array($paymentMethod, $allowedPayment, true)) {
            $this->addFlash('danger', 'Invalid checkout options provided.');
            return $this->redirectToRoute('app_checkout');
        }

        // Load user's cart
        $cart = $this->cartRepo->findOneBy(['user' => $user]);
        if (!$cart || $cart->getCartItems()->count() === 0) {
            $this->addFlash('warning', 'Your cart is empty.');
            return $this->redirectToRoute('app_cart'); // adjust if you have a cart route name
        }

        // Create Order
        $order = new Order();
        $order->setUser($user);
        $order->setCurrency($currency);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setShippingProvider($shippingProvider);
        $order->setDeliveryType($deliveryType);
        $order->setDestination($destination);
        $order->setNotes($notes);
        $order->setPaymentMethod($paymentMethod);
        $order->setPaymentStatus('pending');
        // Status is pending by default in constructor

        $this->em->persist($order);

        // Transform cart items -> order items
        foreach ($cart->getCartItems() as $ci) {
            if (!$ci instanceof CartItem) { continue; }
            $product = $ci->getProduct();
            if (!$product) { continue; }
            $variant = method_exists($ci, 'getProductVariant') ? $ci->getProductVariant() : null;

            $oi = new OrderItem();
            $oi->setRelatedOrder($order);
            $oi->setProduct($product);
            if ($variant && method_exists($oi, 'setProductVariant')) { $oi->setProductVariant($variant); }
            $oi->setQuantity((int)$ci->getQuantity());

            // Use the cart's stored unit price (already includes any discount)
            $price = $ci->getUnitPrice();
            if ($price === null || $price === '') { $price = '0.00'; }
            $oi->setUnitPrice((string)$price);

            // Loyalty points
            $oi->setPointsEarned($oi->calculatePointsEarned());

            $this->em->persist($oi);
            $order->addOrderItem($oi); // also triggers updateTotals
        }

        // Ensure totals are up to date (sum of items)
        $order->updateTotals();
        // Add shipping fee (no discounts in customer side)
        $shippingFee = $order->getShippingFee();
        $order->setTotalAmount(bcadd($order->getTotalAmount() ?? '0.00', $shippingFee, 2));

        $this->em->flush();

        // Clear the cart
        foreach ($cart->getCartItems() as $ci) {
            $cart->removeCartItem($ci);
            $this->em->remove($ci);
        }
        $this->em->flush();

        $this->addFlash('success', 'Order placed successfully!');
        return $this->redirectToRoute('app_checkout_confirmation', ['id' => $order->getId()]);
    }

    #[Route('/checkout/confirmation/{id}', name: 'app_checkout_confirmation', methods: ['GET'])]
    public function confirmation(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }
        $order = $this->em->getRepository(Order::class)->find($id);
        if (!$order || $order->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Order not found');
        }
        return $this->render('checkout/confirmation.html.twig', [
            'order' => $order,
        ]);
    }
}
