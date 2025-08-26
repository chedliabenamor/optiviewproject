<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\CategorypostRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;

class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog_index')]
    public function index(Request $request, PostRepository $postRepository, CategorypostRepository $categorypostRepository, TagRepository $tagRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 5; // Posts per page
        $offset = ($page - 1) * $limit;

        // Get filter parameters
        $categorySlug = $request->query->get('category');
        $tagSlug = $request->query->get('tag');
        $searchQuery = $request->query->get('search');

        // Create query builder for posts
        $queryBuilder = $postRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.tags', 't')
            ->where('p.deletedAt IS NULL');

        // Apply category filter
        if ($categorySlug) {
            $queryBuilder->andWhere('c.slug = :categorySlug')
                        ->setParameter('categorySlug', $categorySlug);
        }

        // Apply tag filter
        if ($tagSlug) {
            $queryBuilder->andWhere('t.slug = :tagSlug')
                        ->setParameter('tagSlug', $tagSlug);
        }

        // Apply search filter
        if ($searchQuery) {
            $queryBuilder->andWhere('(p.title LIKE :searchQuery OR p.content LIKE :searchQuery)')
                        ->setParameter('searchQuery', '%' . $searchQuery . '%');
        }

        $queryBuilder->orderBy('p.createdAt', 'DESC')
                    ->setFirstResult($offset)
                    ->setMaxResults($limit);

        // Create paginator
        $paginator = new Paginator($queryBuilder);
        $totalPosts = count($paginator);
        $totalPages = ceil($totalPosts / $limit);

        $categories = $categorypostRepository->findBy(
            ['deletedAt' => null]
        );

        $tags = $tagRepository->findAll();

        // Get current filter objects for display
        $currentCategory = $categorySlug ? $categorypostRepository->findOneBy(['slug' => $categorySlug]) : null;
        $currentTag = $tagSlug ? $tagRepository->findOneBy(['slug' => $tagSlug]) : null;

        return $this->render('pages/blog/index.html.twig', [
            'posts' => $paginator,
            'categories' => $categories,
            'tags' => $tags,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPosts' => $totalPosts,
            'currentCategory' => $currentCategory,
            'currentTag' => $currentTag,
            'categorySlug' => $categorySlug,
            'tagSlug' => $tagSlug,
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_post_show')]
    public function show(string $slug, PostRepository $postRepository, CategorypostRepository $categorypostRepository, TagRepository $tagRepository): Response
    {
        $post = $postRepository->findOneBy(['slug' => $slug, 'deletedAt' => null]);
        
        if (!$post) {
            throw $this->createNotFoundException('Blog post not found.');
        }

        $categories = $categorypostRepository->findBy(
            ['deletedAt' => null],
            ['name' => 'ASC']
        );

        $tags = $tagRepository->findBy(
            ['deletedAt' => null],
            ['name' => 'ASC']
        );

        // Get approved comments for this post
        $comments = $post->getComments()->filter(
            fn($comment) => $comment->getDeletedAt() === null && $comment->isApproved()
        );

        return $this->render('pages/blog/show.html.twig', [
            'post' => $post,
            'categories' => $categories,
            'tags' => $tags,
            'comments' => $comments,
            'comment_form' => $this->createFormBuilder()
                ->add('comment', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Write your comment here...',
                        'rows' => 3,
                        'class' => 'form-control',
                    ],
                    'constraints' => [
                        new \Symfony\Component\Validator\Constraints\NotBlank([
                            'message' => 'Please enter a comment.',
                        ]),
                    ],
                ])
                ->getForm()
                ->createView(),
        ]);
    }

    #[Route('/blog/{slug}/comment', name: 'blog_comment_add', methods: ['POST'])]
    public function addComment(string $slug, Request $request, PostRepository $postRepository, EntityManagerInterface $entityManager): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            $this->addFlash('danger', 'You must be logged in to post a comment.');
            return $this->redirectToRoute('app_login');
        }

        $post = $postRepository->findOneBy(['slug' => $slug, 'deletedAt' => null]);
        
        if (!$post) {
            throw $this->createNotFoundException('Blog post not found.');
        }

        // Get comment text from form submission
        $formData = $request->request->all('form');
        $commentText = $formData['comment'] ?? '';
        
        if (empty(trim($commentText))) {
            $this->addFlash('danger', 'Comment cannot be empty.');
            return $this->redirectToRoute('blog_post_show', ['slug' => $slug]);
        }

        // Create and persist the new comment
        $comment = new Comment();
        $comment->setAuthor($this->getUser());
        $comment->setContent(trim($commentText));
        $comment->setCreatedAt(new \DateTimeImmutable());
        $comment->setPost($post);
        
        $entityManager->persist($comment);
        $entityManager->flush();
        
        $this->addFlash('success', 'Thank you for your comment!');
        
        return $this->redirectToRoute('blog_post_show', ['slug' => $slug]);
    }
}
