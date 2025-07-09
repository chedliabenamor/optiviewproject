<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class LoyaltyPointsSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleEvent($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleEvent($args);
    }

    private function handleEvent(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        // Assuming 'Confirmed' is the status for a completed order
        if ($entity->getStatus() !== 'Confirmed') {
            return;
        }

        $user = $entity->getUser();
        if (!$user) {
            return;
        }

        $totalPoints = 0;
        foreach ($entity->getOrderItems() as $item) {
            $product = $item->getProductVariant()->getProduct();
            if ($product && $product->getLoyaltyPoints() > 0) {
                $totalPoints += $product->getLoyaltyPoints() * $item->getQuantity();
            }
        }

        if ($totalPoints > 0) {
            $currentPoints = $user->getLoyaltyPoints() ?? 0;
            $user->setLoyaltyPoints($currentPoints + $totalPoints);

            // We need to persist the User entity changes
            $entityManager = $args->getObjectManager();
            $entityManager->persist($user);
            $entityManager->flush();
        }
    }
}
