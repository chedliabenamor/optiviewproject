<?php

namespace App\EventSubscriber;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Wishlist;
use App\Entity\WishlistItem;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginRedirectSubscriber implements EventSubscriberInterface
{
    use TargetPathTrait;

    private UrlGeneratorInterface $urlGenerator;
    private RequestStack $requestStack;
    private EntityManagerInterface $entityManager;
    private CartRepository $cartRepository;
    private WishlistRepository $wishlistRepository;
    private ProductRepository $productRepository;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
        EntityManagerInterface $entityManager,
        CartRepository $cartRepository,
        WishlistRepository $wishlistRepository,
        ProductRepository $productRepository
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
        $this->cartRepository = $cartRepository;
        $this->wishlistRepository = $wishlistRepository;
        $this->productRepository = $productRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return; // Should not happen on LoginSuccessEvent
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Note: Guest cart and wishlist merge is handled by JavaScript
        // calling /api/cart/merge and /api/wishlist/merge endpoints
        // No server-side merge needed since data is in localStorage

        // 1) If a protected URL triggered the login (e.g., /checkout, /profile), redirect back to it
        if ($session) {
            $targetPath = $this->getTargetPath($session, 'main');
            if ($targetPath) {
                $event->setResponse(new RedirectResponse($targetPath));
                return;
            }
        }

        // 2) No saved target path: redirect based on role
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $redirectUrl = $this->urlGenerator->generate('admin_dashboard');
        } else {
            $redirectUrl = $this->urlGenerator->generate('app_home');
        }

        $event->setResponse(new RedirectResponse($redirectUrl));
    }

}
