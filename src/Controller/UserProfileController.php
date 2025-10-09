<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Form\UserProfileTypeForm;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route; // Ensuring Attribute is used for #[Route]

#[Route('/profile')]
final class UserProfileController extends AbstractController
{
    #[Route('/', name: 'app_user_profile_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        // Sync loyalty points from shipped/delivered orders
        $orders = $orderRepository->findBy(['user' => $user]);
        $computed = 0;
        foreach ($orders as $o) {
            $status = strtolower((string)$o->getStatus());
            if (in_array($status, ['shipped', 'delivered'], true)) {
                $computed += (int)$o->getTotalPointsEarned();
            }
        }
        if ((int)($user->getLoyaltyPoints() ?? 0) !== $computed) {
            $user->setLoyaltyPoints($computed);
            $entityManager->flush();
        }
        return $this->render('user_profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_user_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserProfileTypeForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password change separately if a new password is provided
            $newPassword = $form->get('plainPassword')->getData();
            if ($newPassword) {
                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $newPassword
                    )
                );
            }

            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated successfully.');

            return $this->redirectToRoute('app_user_profile_index');
        }

        return $this->render('user_profile/edit.html.twig', [
            'userProfileForm' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/orders', name: 'app_user_profile_orders', methods: ['GET'])]
    public function orderHistory(OrderRepository $orderRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $orders = $orderRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        // Ensure points are in sync here as well
        $computed = 0;
        foreach ($orders as $o) {
            $status = strtolower((string)$o->getStatus());
            if (in_array($status, ['shipped', 'delivered'], true)) {
                $computed += (int)$o->getTotalPointsEarned();
            }
        }
        if ((int)($user->getLoyaltyPoints() ?? 0) !== $computed) {
            $user->setLoyaltyPoints($computed);
            $entityManager->flush();
        }

        return $this->render('user_profile/orders.html.twig', [
            'orders' => $orders,
            'user' => $user,
        ]);
    }

    #[Route('/orders/{id}', name: 'app_user_order_detail', methods: ['GET'])]
    public function orderDetail(Order $order): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($order->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('user_profile/order_detail.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/orders/{id}/cancel', name: 'app_user_order_cancel', methods: ['POST'])]
    public function cancelOrder(Order $order, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        if ($order->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel-order-' . $order->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        if (strtolower((string)$order->getStatus()) !== Order::STATUS_PENDING) {
            $this->addFlash('warning', 'Only pending orders can be cancelled.');
            return $this->redirectToRoute('app_user_order_detail', ['id' => $order->getId()]);
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $entityManager->flush();

        $this->addFlash('success', 'Order cancelled.');
        return $this->redirectToRoute('app_user_order_detail', ['id' => $order->getId()]);
    }

}
