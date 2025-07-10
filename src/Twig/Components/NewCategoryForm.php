<?php

namespace App\Twig\Components;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[AsLiveComponent('new_category_form')]
class NewCategoryForm
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Category name cannot be blank')]
    #[Assert\Length(min: 2, max: 255, 
        minMessage: 'Category name must be at least {{ limit }} characters long',
        maxMessage: 'Category name cannot be longer than {{ limit }} characters'
    )]
    public string $name = '';

    private bool $success = false;

    #[LiveAction]
    public function save(EntityManagerInterface $em)
    {
        // Validate the form
        $this->validate();
        
        if (count($this->getFormErrors()) > 0) {
            return;
        }

        try {
            $category = new Category();
            $category->setName($this->name);
            $em->persist($category);
            $em->flush();

            // Emit event for the front-end
            $this->emit('category:created', [
                'id' => $category->getId(),
                'name' => $category->getName()
            ]);

            // Reset form
            $this->success = true;
            $this->name = '';
            $this->resetValidation();

        } catch (\Exception $e) {
            $this->addError('name', 'Failed to create category. Please try again.');
        }
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context = null): void
    {
        if (null === $context) {
            return;
        }

        // Add custom validation if needed
        if (strlen(trim($this->name)) === 0) {
            $context->buildViolation('Category name cannot be empty')
                ->atPath('name')
                ->addViolation();
        }
    }
}
