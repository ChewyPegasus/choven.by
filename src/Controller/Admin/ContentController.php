<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FaqItem;
use App\Entity\Post;
use App\Form\Admin\FaqItemType;
use App\Form\Admin\PostType;
use App\Repository\FaqItemRepository;
use App\Repository\PostRepository;
use App\Service\ImageUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Blog posts and FAQ.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ContentController extends AbstractController
{
    #[Route('/posts', name: 'app_admin_posts', methods: ['GET'])]
    public function posts(Request $request, PostRepository $posts): Response
    {
        return $this->render('admin/post/index.html.twig', ['page' => $posts->findAdminPage($request->query->getInt('page', 1))]);
    }

    #[Route('/posts/new', name: 'app_admin_post_new', methods: ['GET', 'POST'])]
    public function newPost(Request $request, PostRepository $posts, ImageUploader $uploader): Response
    {
        return $this->handlePost(new Post(), $request, $posts, $uploader, 'admin.posts.flash.created');
    }

    #[Route('/posts/{id}/edit', name: 'app_admin_post_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editPost(Post $post, Request $request, PostRepository $posts, ImageUploader $uploader): Response
    {
        return $this->handlePost($post, $request, $posts, $uploader, 'admin.posts.flash.saved');
    }

    #[Route('/posts/{id}/delete', name: 'app_admin_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePost(Post $post, Request $request, PostRepository $posts, ImageUploader $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete-post-'.$post->getId(), $request->getPayload()->getString('_token'))) {
            $uploader->remove($post->getCoverImage());
            $posts->remove($post);
            $this->addFlash('success', 'admin.posts.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_posts');
    }

    #[Route('/faq', name: 'app_admin_faq', methods: ['GET'])]
    public function faq(FaqItemRepository $faq): Response
    {
        return $this->render('admin/faq/index.html.twig', ['items' => $faq->findAllOrdered()]);
    }

    #[Route('/faq/new', name: 'app_admin_faq_new', methods: ['GET', 'POST'])]
    public function newFaq(Request $request, FaqItemRepository $faq): Response
    {
        return $this->handleFaq((new FaqItem())->setPosition($faq->getNextPosition()), $request, $faq, 'admin.faq.flash.created');
    }

    #[Route('/faq/{id}/edit', name: 'app_admin_faq_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editFaq(FaqItem $item, Request $request, FaqItemRepository $faq): Response
    {
        return $this->handleFaq($item, $request, $faq, 'admin.faq.flash.saved');
    }

    #[Route('/faq/{id}/delete', name: 'app_admin_faq_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteFaq(FaqItem $item, Request $request, FaqItemRepository $faq): Response
    {
        if ($this->isCsrfTokenValid('delete-faq-'.$item->getId(), $request->getPayload()->getString('_token'))) {
            $faq->remove($item);
            $this->addFlash('success', 'admin.faq.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_faq');
    }

    private function handlePost(Post $post, Request $request, PostRepository $posts, ImageUploader $uploader, string $successMessage): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('coverFile')->getData();
            if ($file instanceof UploadedFile) {
                $previous = $post->getCoverImage();
                $post->setCoverImage($uploader->upload($file, 'posts'));
                $uploader->remove($previous);
            }
            $posts->save($post);
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_posts');
        }

        return $this->render('admin/post/form.html.twig', ['form' => $form, 'post' => $post]);
    }

    private function handleFaq(FaqItem $item, Request $request, FaqItemRepository $faq, string $successMessage): Response
    {
        $form = $this->createForm(FaqItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $faq->save($item);
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_faq');
        }

        return $this->render('admin/faq/form.html.twig', ['form' => $form, 'item' => $item]);
    }
}
