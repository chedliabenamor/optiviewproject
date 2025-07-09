<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class UserManager
{
    private UserPasswordHasherInterface $passwordHasher;
    private EntityManagerInterface $entityManager;

    public function __construct(UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager)
    {
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
    }

    public function encodePassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();
        if ($plainPassword !== null && $plainPassword !== '') {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $user->eraseCredentials();
        }
    }

    public function activateUser(User $user): void
    {
        $user->setActive(true);
        $this->entityManager->flush();
    }

    public function deactivateUser(User $user): void
    {
        $user->setActive(false);
        $this->entityManager->flush();
    }
}
