<?php

namespace App\Form;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\EventSubscriber\OrderItemVariantSubscriber;
use Doctrine\ORM\EntityManagerInterface;

class OrderItemType extends AbstractType
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a product',
                'required' => false,
                'help' => 'Choose either a product or a variant. Not both.',
                'attr' => [
                    'data-controller' => 'order-item-form',
                    'data-action' => 'change->order-item-form#onProductSelect',
                    'data-order-item-form-target' => 'productSelect',
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'attr' => ['min' => 1],
                'data' => 1,
            ]);

        $builder->addEventSubscriber(new OrderItemVariantSubscriber($this->em));
        $builder->addEventSubscriber(new \App\Form\EventSubscriber\OrderItemProductOrVariantExclusiveSubscriber());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderItem::class,
        ]);
    }
}
