<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
// use Symfony\Component\Security\Http\SecurityEvents; // Not strictly needed if using LoginSuccessEvent::class

class LoginRedirectSubscriber implements EventSubscriberInterface
{
    use TargetPathTrait;

    private UrlGeneratorInterface $urlGenerator;
    private RequestStack $requestStack;

    public function __construct(UrlGeneratorInterface $urlGenerator, RequestStack $requestStack)
    {
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
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

        // 1) If a protected URL triggered the login (e.g., /checkout), redirect back to it
        $session = $this->requestStack->getSession();
        if ($session) {
            $targetPath = $this->getTargetPath($session, 'main');
            if ($targetPath) {
                $event->setResponse(new RedirectResponse($targetPath));
                return;
            }
        }

        // 2) Otherwise, always send authenticated users to checkout page
        try {
            $redirectUrl = $this->urlGenerator->generate('app_checkout');
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
            $redirectUrl = '/checkout';
        }

        $event->setResponse(new RedirectResponse($redirectUrl));
    }
}
