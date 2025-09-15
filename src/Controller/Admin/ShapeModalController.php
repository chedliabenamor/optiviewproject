<?php

namespace App\Controller\Admin;

use App\Entity\Shape;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ShapeModalController extends AbstractController
{
    #[Route('/admin-ajax/shape/new', name: 'admin_shape_new_modal')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $shape = new Shape();

        $form = $this->createFormBuilder($shape)
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Name',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($shape);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'entity' => [
                    'id' => $shape->getId(),
                    'name' => $shape->getName(),
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
