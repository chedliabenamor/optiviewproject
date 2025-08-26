<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            // The user is deactivated, deny access
            throw new CustomUserMessageAuthenticationException(
                'Your account is currently inactive. Please contact our support team to activate your account.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // We don't need to do anything here for this feature.
        // This method is called after the user has been authenticated.
    }
}
