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

#[AsLiveComponent('new_category_form')]
class NewCategoryForm
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank]
    public string $name = '';

    #[LiveAction]
    public function save(EntityManagerInterface $em)
    {
        $this->validate();
        $category = new Category();
        $category->setName($this->name);
        $em->persist($category);
        $em->flush();

        $this->emit('category:created', ['id' => $category->getId(), 'name' => $category->getName()]);
        $this->dispatchBrowserEvent('modal:close');
        $this->name = '';
        $this->resetValidation();
    }
}
