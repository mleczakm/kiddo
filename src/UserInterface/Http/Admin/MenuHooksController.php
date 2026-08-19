<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use App\Repository\MenuHookLinkRepository;
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

    /** Slot keys and their display labels */
    private const array SLOT_KEYS = [
        'main_nav_extra' => 'Nav główna — po logowaniu',
        'main_nav_before_auth' => 'Nav główna — przed zalogowaniem',
        'footer_links' => 'Stopka strony',
    ];

    #[Route('/admin/menu-hooks', name: 'app_admin_menu_hooks', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleCreate($request);
        }

        $links = $this->repository->findBy([], ['slotKey' => 'ASC', 'position' => 'ASC']);

        return $this->render('admin/menu-hooks/index.html.twig', [
            'slots' => self::SLOT_KEYS,
            'links' => $links,
        ]);
    }

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

    private function handleCreate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_menu_hook', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $slotKey = (string) $request->request->get('slotKey');
            if (!isset(self::SLOT_KEYS[$slotKey])) {
                throw new \InvalidArgumentException('Invalid slot key.');
            }

            $targetType = MenuHookLinkTarget::from((string) $request->request->get('targetType'));
            $target = trim((string) $request->request->get('target'));
            $label = trim((string) $request->request->get('label'));

            if ($target === '') {
                throw new \InvalidArgumentException('Target is required.');
            }
            if ($label === '') {
                throw new \InvalidArgumentException('Label is required.');
            }

            $maxPosition = $this->repository
                ->createQueryBuilder('m')
                ->select('MAX(m.position)')
                ->where('m.slotKey = :slot')
                ->setParameter('slot', $slotKey)
                ->getQuery()
                ->getSingleScalarResult() ?? -1;

            $link = new MenuHookLink($slotKey, (int) $maxPosition + 1, $targetType, $target, $label);
            $this->em->persist($link);
            $this->em->flush();

            $this->addFlash('success', 'Link został dodany.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_menu_hooks');
    }
}
