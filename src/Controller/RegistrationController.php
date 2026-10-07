<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\Data\RegistrationData;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use App\Service\UserRegistrar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserRegistrar $registrar, Security $security): Response
    {
        $data = new RegistrationData();
        // Coming from an invitation link: the invited address is already filled in.
        $data->email = mb_substr($request->query->getString('email'), 0, 180);
        $form = $this->createForm(RegistrationFormType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('registration/register.html.twig', ['form' => $form]);
        }

        $user = $registrar->register($data);
        $this->addFlash('success', 'flash.registration.success');

        return $security->login($user, 'form_login', 'main') ?? $this->redirectToRoute('app_home');
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($request->query->getInt('id'));
        if (null === $user) {
            throw $this->createNotFoundException();
        }

        try {
            $this->emailVerifier->confirm($request, $user);
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'flash.verify_email.invalid_link');

            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('success', 'flash.verify_email.success');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[IsCsrfTokenValid('resend-verification')]
    public function resendVerification(#[CurrentUser] User $user): Response
    {
        if (!$user->isVerified()) {
            $this->emailVerifier->sendVerificationEmail($user);
            $this->addFlash('success', 'flash.verify_email.resent');
        }

        return $this->redirectToRoute('app_home');
    }
}
