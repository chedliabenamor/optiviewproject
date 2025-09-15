<?php

namespace App\Controller\Admin;

use App\Entity\Genre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class GenreModalController extends AbstractController
{
    #[Route('/admin-ajax/genre/new', name: 'admin_genre_new_modal')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $genre = new Genre();

        $form = $this->createFormBuilder($genre)
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Name',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($genre);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'entity' => [
                    'id' => $genre->getId(),
                    'name' => $genre->getName(),
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
