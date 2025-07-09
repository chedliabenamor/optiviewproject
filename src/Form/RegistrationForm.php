<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;

class RegistrationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your first name',
                    ]),
                ],
            ])
            ->add('lastname', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your last name',
                    ]),
                ],
            ])
            ->add('email') // Email field remains as is
            ->add('phone', TextType::class, [
                'required' => false, // Set to true if phone is mandatory
                'label' => 'Phone Number (Optional)',
            ])
            ->add('sex', ChoiceType::class, [
                'choices'  => [
                    'Male' => 'male',
                    'Female' => 'female',
                    'Other' => 'other',
                ],
                'required' => false, // Set to true if sex is mandatory
                'placeholder' => 'Select your sex (Optional)',
                'label' => 'Sex (Optional)',
            ])
            ->add('birthdate', BirthdayType::class, [
                'widget' => 'choice', // Or 'single_text' if you prefer a date picker input
                'required' => false, // Set to true if birthdate is mandatory
                'placeholder' => [
                    'year' => 'Year', 'month' => 'Month', 'day' => 'Day',
                ],
                'label' => 'Birthdate (Optional)',
                // Add years to range from, for example, 100 years ago to 10 years ago
                'years' => range(date('Y') - 100, date('Y') - 10),
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
