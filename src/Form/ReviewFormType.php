<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('comment', TextareaType::class, [
            'label' => 'Your review',
            'required' => true,
            'attr' => [
                'rows' => 4,
                'class' => 'size-110 bor8 stext-102 cl2 p-lr-20 p-tb-10',
                'placeholder' => 'Write your comment...'
            ],
        ])
        ->add('rating', HiddenType::class, [
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Standalone simple form, not mapped to an entity directly
            'csrf_protection' => true,
        ]);
    }
}
