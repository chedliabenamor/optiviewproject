<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
// use Symfony\Component\Security\Http\SecurityEvents; // Not strictly needed if using LoginSuccessEvent::class

class LoginRedirectSubscriber implements EventSubscriberInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(UrlGeneratorInterface $urlGenerator, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->urlGenerator = $urlGenerator;
        $this->authorizationChecker = $authorizationChecker;
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

        // Placeholder route names - these will need to exist
        $adminRoute = 'admin_dashboard'; // Replace with your actual admin route name
        $userRoute = 'app_home';       // Replace with your actual homepage route name
        $fallbackRoute = '/'; // Default fallback if specific routes are not found

        $redirectUrl = $fallbackRoute; // Initialize with a safe default

        // Check if the user has ROLE_ADMIN
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            try {
                $redirectUrl = $this->urlGenerator->generate($adminRoute);
            } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
                // Admin route not found, try user route as fallback
                try {
                    $redirectUrl = $this->urlGenerator->generate($userRoute);
                } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e2) {
                    // User route also not found, use the root fallback
                    // Optionally log this situation: e.g., $this->logger->warning('Admin or User route not found for admin login, falling back to /');
                }
            }
        } else {
            // For any other authenticated user, redirect to the homepage
            try {
                $redirectUrl = $this->urlGenerator->generate($userRoute);
            } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
                // User route not found, use the root fallback
                // Optionally log this situation: e.g., $this->logger->warning('User route not found for user login, falling back to /');
            }
        }
        
        $response = new RedirectResponse($redirectUrl);
        $event->setResponse($response);
    }
}
