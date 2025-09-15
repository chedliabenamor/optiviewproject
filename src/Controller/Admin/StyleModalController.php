<?php

namespace App\Controller\Admin;

use App\Entity\Style;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class StyleModalController extends AbstractController
{
    #[Route('/admin-ajax/style/new', name: 'admin_style_new_modal')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $style = new Style();

        $form = $this->createFormBuilder($style)
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Name',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($style);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'entity' => [
                    'id' => $style->getId(),
                    'name' => $style->getName(),
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
