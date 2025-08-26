<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        $content = $this->twig->render('bundles/TwigBundle/Exception/error403.html.twig', [
            'status_code' => 403,
            'status_text' => 'Forbidden',
            'exception' => $accessDeniedException,
        ]);

        return new Response($content, 403);
    }
}
