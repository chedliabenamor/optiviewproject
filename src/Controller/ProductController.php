<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Review;
use App\Form\ReviewFormType;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    #[Route('/product/{id}', name: 'product_show', requirements: ['id' => '\\d+'])]
    public function show(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository
    ): Response {
        // Build simple comment-only form
        $form = $this->createForm(ReviewFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Require authentication
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('warning', 'Please log in to submit a review.');
                return $this->redirectToRoute('app_login');
            }

            $data = $form->getData();
            $comment = $data['comment'] ?? null;
            $rating = isset($data['rating']) && $data['rating'] !== '' ? (int) $data['rating'] : null;
            if ($rating !== null) {
                // clamp to 1..5
                if ($rating < 1) { $rating = 1; }
                if ($rating > 5) { $rating = 5; }
            }
            if ($comment) {
                $review = new Review();
                $review->setProduct($product);
                $review->setUser($user);
                $review->setComment($comment);
                if ($rating !== null) { $review->setRating($rating); }
                // default to moderation: require admin approval
                $review->setIsApproved(false);
                $em->persist($review);
                $em->flush();
                $this->addFlash('success', 'Thanks! Your review was submitted and will be visible once approved.');
            }

            return $this->redirectToRoute('product_show', ['id' => $product->getId()]);
        }

        // Fetch approved reviews for display
        $reviews = $reviewRepository->findBy([
            'product' => $product,
            'isApproved' => true,
        ], ['createdAt' => 'DESC']);

        return $this->render('pages/product/show.html.twig', [
            'product' => $product,
            'reviewForm' => $form->createView(),
            'reviews' => $reviews,
        ]);
    }

    #[Route('/api/products/{id}/quick-view', name: 'product_quick_view', methods: ['GET'])]
    public function quickView(Product $product, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->normalize($product, 'json', [
            'groups' => 'product_quick_view',
        ]);

        return new JsonResponse($data);
    }
}
