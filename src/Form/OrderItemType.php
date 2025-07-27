<?php

namespace App\Form;

use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('productVariant', EntityType::class, [
                'class' => ProductVariant::class,
                'placeholder' => 'Search and select a product variant...',
                'attr' => [
                    'data-ea-widget' => 'ea-autocomplete',
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'data' => 1,
                'attr' => ['min' => 1],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderItem::class,
        ]);
    }
}