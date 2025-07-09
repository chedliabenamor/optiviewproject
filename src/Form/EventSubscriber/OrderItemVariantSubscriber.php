<?php

namespace App\Form\EventSubscriber;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\OrderItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityManagerInterface;

class OrderItemVariantSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
        ];
    }

    public function onPreSetData(FormEvent $event): void
    {
        $form = $event->getForm();
        /** @var OrderItem|null $data */
        $data = $event->getData();
        $product = $data ? $data->getProduct() : null;
        $this->addVariantField($form, $product);
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();
        $product = null;
        if (isset($data['product']) && $data['product']) {
            $product = $this->em->getRepository(Product::class)->find($data['product']);
        }
        $this->addVariantField($form, $product);
    }

    private function addVariantField($form, ?Product $product): void
    {
        $variants = $product ? $product->getProductVariants() : [];
        $form->add('productVariant', EntityType::class, [
            'class' => ProductVariant::class,
            'choices' => $variants,
            'choice_label' => function (ProductVariant $variant) {
                return (string) $variant;
            },
            'placeholder' => $product ? 'Select a variant' : 'Select a product first',
            'required' => false,
        ]);
    }
}
