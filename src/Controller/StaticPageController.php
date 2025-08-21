<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticPageController extends AbstractController
{
    #[Route('/help-faqs', name: 'app_static_help_faqs')]
    public function helpFaqs(): Response
    {
        return $this->render('pages/static/help_faqs.html.twig', [
            'page_title' => 'Help & FAQs',
        ]);
    }

    #[Route('/about', name: 'app_static_about')]
    public function about(): Response
    {
        return $this->render('pages/static/about.html.twig', [
            'page_title' => 'About Us',
        ]);
    }

    #[Route('/blog', name: 'app_static_blog')]
    public function blog(): Response
    {
        // For now, a simple page. Later, this could list blog posts.
        return $this->render('pages/static/blog.html.twig', [
            'page_title' => 'Our Blog',
        ]);
    }

    #[Route('/policy', name: 'app_static_policy')]
    public function policy(): Response
    {
        return $this->render('pages/static/privacy_policy.html.twig', [
            'page_title' => 'Our Privacy Policy',
        ]);
    }
}
