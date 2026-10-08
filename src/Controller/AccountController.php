<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountPasswordFormType;
use App\Form\AccountProfileFormType;
use App\Form\Data\AccountPasswordData;
use App\Form\Data\AccountProfileData;
use App\Service\AccountManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * "Mon compte": name and password. Two independent forms on the same page.
 */
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountManager $accountManager,
    ) {
    }

    #[Route('/account', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(Request $request, Security $security, #[CurrentUser] User $user): Response
    {
        $profile = $this->createForm(AccountProfileFormType::class, AccountProfileData::fromUser($user))->handleRequest($request);
        if ($profile->isSubmitted() && $profile->isValid()) {
            $this->accountManager->updateProfile($user, $profile->getData());

            return $this->done('flash.account.profile_updated');
        }

        $password = $this->createForm(AccountPasswordFormType::class, new AccountPasswordData())->handleRequest($request);
        if ($password->isSubmitted() && $password->isValid()) {
            $this->accountManager->changePassword($user, $password->getData()->plainPassword);
            // A new password logs out every session, this one included: sign it back in.
            $security->login($user, 'form_login', 'main');

            return $this->done('flash.account.password_changed');
        }

        $status = $profile->isSubmitted() || $password->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('account/index.html.twig', ['profile' => $profile, 'password' => $password], new Response(status: $status));
    }

    private function done(string $flash): Response
    {
        $this->addFlash('success', $flash);

        return $this->redirectToRoute('app_account');
    }
}
