<?php

namespace App\Component;

use App\Entity\Category;
use App\Form\CategoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'CategoryFormComponent', template: 'components/category_form.html.twig')]
class CategoryFormComponent extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public ?Category $initialData = null;

    #[LiveProp]
    public bool $success = false;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(CategoryType::class, $this->initialData);
    }

    #[LiveAction]
    public function save()
    {
        $this->submitForm();

        /** @var Category $category */
        $category = $this->getForm()->getData();

        if ($this->isFormSubmitted() && $this->isFormValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->success = true;

            $this->dispatchBrowserEvent('category:created');
        }
    }
}
