<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductVariantController extends AbstractController
{
    #[Route('/admin/product/variant/{id}/archive', name: 'admin_product_variant_archive', methods: ['POST'])]
    public function archive(Request $request, ProductVariant $variant, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('archive' . $variant->getId(), $submittedToken)) {
            $variant->setDeletedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Variant has been archived.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirect($request->headers->get('referer'));
    }
}
