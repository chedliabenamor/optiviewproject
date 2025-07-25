<?php

namespace App\Form\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;

class OrderItemProductOrVariantExclusiveSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT => 'validateExclusive',
        ];
    }

    public function validateExclusive(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();
        $product = $data->getProduct();
        $variant = $data->getProductVariant();

        if ($product && $variant) {
            $form->addError(new FormError('Please select either a product OR a variant, not both.'));
        }
        if (!$product && !$variant) {
            $form->addError(new FormError('Please select a product OR a variant.'));
        }
    }
}
