<?php

namespace App\Controller\Admin;

use App\Entity\Brand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class BrandModalController extends AbstractController
{
    #[Route('/admin-ajax/brand/new', name: 'admin_brand_new_modal')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $brand = new Brand();

        $form = $this->createFormBuilder($brand)
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Name',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($brand);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'entity' => [
                    'id' => $brand->getId(),
                    'name' => $brand->getName(),
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
