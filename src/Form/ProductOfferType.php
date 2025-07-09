<?php

namespace App\Form;

use App\Entity\ProductOffer;
use App\Entity\Product;
use App\Entity\ProductVariant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

class ProductOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('product', Select2EntityType::class, [
                'multiple' => false,
                'remote_route' => 'select2_product',
                'class' => Product::class,
                'primary_key' => 'id',
                'text_property' => 'name',
                'allow_clear' => true,
                'placeholder' => 'Select a product',
                'required' => true,
            ])
            ->add('productVariant', Select2EntityType::class, [
                'multiple' => false,
                'remote_route' => 'select2_product_variant_by_product',
                'class' => ProductVariant::class,
                'primary_key' => 'id',
                'text_property' => 'sku',
                'allow_clear' => true,
                'placeholder' => 'Select a variant',
                'required' => false,
                'delay' => 250,
                'data' => null,
                'remote_params' => ['product' => 'product'],
            ])
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, ['required' => false])
            ->add('discountType', ChoiceType::class, [
                'choices' => [
                    'Percentage' => ProductOffer::TYPE_PERCENTAGE,
                    'Fixed Amount' => ProductOffer::TYPE_FIXED,
                ],
            ])
            ->add('discountValue', NumberType::class)
            ->add('startDate', DateTimeType::class)
            ->add('endDate', DateTimeType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProductOffer::class,
        ]);
    }
}
