<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\UserAdminVoter;
use App\Service\UserAdministrator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserAdministrator $administrator,
    ) {
    }

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(UserRepository $userRepository, #[MapQueryParameter] string $q = '', #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1): Response
    {
        $search = mb_substr(trim($q), 0, 100);

        return $this->render('admin/users.html.twig', ['search' => $search, 'page' => $page] + $userRepository->findPageForAdmin($search, $page));
    }

    #[Route('/{id}/verify', name: 'app_admin_user_verify', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserAdminVoter::VERIFY, 'user')]
    #[IsCsrfTokenValid(new Expression('"admin-user-" ~ args["user"].getId()'))]
    public function verify(User $user): Response
    {
        $this->administrator->verify($user);

        return $this->done('flash.admin.verified');
    }

    #[Route('/{id}/toggle-active', name: 'app_admin_user_toggle_active', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserAdminVoter::TOGGLE_ACTIVE, 'user')]
    #[IsCsrfTokenValid(new Expression('"admin-user-" ~ args["user"].getId()'))]
    public function toggleActive(User $user): Response
    {
        $this->administrator->toggleActive($user);

        return $this->done($user->isActive() ? 'flash.admin.activated' : 'flash.admin.deactivated');
    }

    #[Route('/{id}/toggle-admin', name: 'app_admin_user_toggle_admin', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserAdminVoter::TOGGLE_ADMIN, 'user')]
    #[IsCsrfTokenValid(new Expression('"admin-user-" ~ args["user"].getId()'))]
    public function toggleAdmin(User $user): Response
    {
        $this->administrator->toggleAdmin($user);

        return $this->done($user->isAdmin() ? 'flash.admin.promoted' : 'flash.admin.demoted');
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserAdminVoter::DELETE, 'user')]
    #[IsCsrfTokenValid(new Expression('"admin-user-" ~ args["user"].getId()'))]
    public function delete(User $user): Response
    {
        $this->administrator->delete($user);

        return $this->done('flash.admin.deleted');
    }

    private function done(string $flash): Response
    {
        $this->addFlash('success', $flash);

        return $this->redirectToRoute('app_admin_users');
    }
}
