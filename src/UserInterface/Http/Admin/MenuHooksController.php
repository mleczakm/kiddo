<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use App\Entity\MenuHookSlot;
use App\Infrastructure\Doctrine\Repository\MenuHookLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGE_CONTENT')]
final class MenuHooksController extends AbstractController
{
    public function __construct(
        private readonly MenuHookLinkRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @throws \Throwable */
    #[Route('/admin/menu-hooks', name: 'app_admin_menu_hooks', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleCreate($request);
        }

        $links = $this->repository->findBy([], ['slotKey' => 'ASC', 'position' => 'ASC']);

        $slots = [];
        foreach (MenuHookSlot::cases() as $slot) {
            $slots[$slot->value] = $slot->label();
        }

        return $this->render('admin/menu-hooks/index.html.twig', [
            'slots' => $slots,
            'links' => $links,
        ]);
    }

    /** @throws \Throwable */
    #[Route('/admin/menu-hooks/{id}/delete', name: 'app_admin_menu_hook_delete', methods: ['POST'])]
    public function delete(MenuHookLink $link, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(
            'delete_menu_hook_' . (string) $link->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->em->remove($link);
        $this->em->flush();
        $this->addFlash('success', 'Link został usunięty.');

        return $this->redirectToRoute('app_admin_menu_hooks');
    }

    /** @throws \Throwable */
    private function handleCreate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_menu_hook', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $slotKey = (string) $request->request->get('slotKey');
            if (MenuHookSlot::tryFrom($slotKey) === null) {
                throw new \InvalidArgumentException('Invalid slot key.');
            }

            $targetType = MenuHookLinkTarget::tryFrom((string) $request->request->get('targetType'));
            if ($targetType === null) {
                throw new \InvalidArgumentException('Invalid target type.');
            }

            $target = trim((string) $request->request->get('target'));
            $label = trim((string) $request->request->get('label'));

            if ($target === '') {
                throw new \InvalidArgumentException('Target is required.');
            }
            if ($label === '') {
                throw new \InvalidArgumentException('Label is required.');
            }

            $link = new MenuHookLink(
                $slotKey,
                $this->repository->nextPositionForSlot($slotKey),
                $targetType,
                $target,
                $label,
            );
            $this->em->persist($link);
            $this->em->flush();

            $this->addFlash('success', 'Link został dodany.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_menu_hooks');
    }
}
