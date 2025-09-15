<?php

namespace App\Controller\Admin;

use App\Entity\Color;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ColorModalController extends AbstractController
{
    #[Route('/admin-ajax/color/new', name: 'admin_color_new_modal')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $color = new Color();

        $builder = $this->createFormBuilder($color)
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Name',
            ]);

        if (property_exists(Color::class, 'imageFile')) {
            $builder->add('imageFile', VichImageType::class, [
                'required' => false,
                'label' => 'Image',
                'allow_delete' => false,
                'download_uri' => false,
            ]);
        }

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($color);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'entity' => [
                    'id' => $color->getId(),
                    'name' => $color->getName(),
                ],
            ]);
        }

        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('admin/category/modal.html.twig', [
                'form' => $form->createView(),
            ]);
            return new Response($html);
        }

        return $this->render('admin/category/modal.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
