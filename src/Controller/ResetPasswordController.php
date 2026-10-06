<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Form\Data\ChangePasswordData;
use App\Form\Data\ResetPasswordRequestData;
use App\Form\ResetPasswordRequestFormType;
use App\Service\PasswordResetter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly PasswordResetter $passwordResetter,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $data = new ResetPasswordRequestData();
        $form = $this->createForm(ResetPasswordRequestFormType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('reset_password/request.html.twig', ['form' => $form]);
        }

        $this->setTokenObjectInSession($this->passwordResetter->sendResetLink($data->email));

        return $this->redirectToRoute('app_check_email');
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        $resetToken = $this->getTokenObjectFromSession();
        if (null === $resetToken) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('reset_password/check_email.html.twig', ['resetToken' => $resetToken]);
    }

    /**
     * The token is moved from the URL to the session so it cannot leak
     * through the Referer header or browser history.
     */
    #[Route('/reset/{token}', name: 'app_reset_password_token', methods: ['GET'])]
    public function storeToken(string $token): Response
    {
        $this->storeTokenInSession($token);

        return $this->redirectToRoute('app_reset_password');
    }

    #[Route('/reset', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request): Response
    {
        $token = $this->getTokenFromSession() ?? throw $this->createNotFoundException();

        try {
            $user = $this->passwordResetter->findUserByToken($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', 'flash.reset_password.invalid_token');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $data = new ChangePasswordData();
        $form = $this->createForm(ChangePasswordFormType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('reset_password/reset.html.twig', ['form' => $form]);
        }

        $this->passwordResetter->resetPassword($token, $user, $data->plainPassword);
        $this->cleanSessionAfterReset();
        $this->addFlash('success', 'flash.reset_password.success');

        return $this->redirectToRoute('app_login');
    }
}
