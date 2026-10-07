<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Data\SmtpSettingsData;
use App\Form\SmtpSettingsFormType;
use App\Repository\SmtpSettingsRepository;
use App\Service\SmtpSettingsManager;
use App\Service\SmtpTester;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/smtp')]
#[IsGranted('ROLE_ADMIN')]
final class AdminSmtpController extends AbstractController
{
    #[Route('', name: 'app_admin_smtp', methods: ['GET', 'POST'])]
    public function edit(Request $request, SmtpSettingsRepository $settingsRepository, SmtpSettingsManager $settingsManager): Response
    {
        $settings = $settingsRepository->findOrCreate();
        $data = SmtpSettingsData::fromSettings($settings);
        $form = $this->createForm(SmtpSettingsFormType::class, $data, ['has_password' => $settings->hasPassword()])->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('admin/smtp.html.twig', ['form' => $form, 'settings' => $settings]);
        }

        $settingsManager->save($data);
        $this->addFlash('success', 'flash.admin.smtp_saved');

        return $this->redirectToRoute('app_admin_smtp');
    }

    #[Route('/test', name: 'app_admin_smtp_test', methods: ['POST'])]
    #[IsCsrfTokenValid('admin-smtp-test')]
    public function test(SmtpSettingsRepository $settingsRepository, SmtpTester $tester, TranslatorInterface $translator, #[CurrentUser] User $admin): Response
    {
        $settings = $settingsRepository->findCurrent();
        if (null === $settings || !$settings->isConfigured()) {
            $this->addFlash('error', 'flash.admin.smtp_not_configured');

            return $this->redirectToRoute('app_admin_smtp');
        }

        try {
            $tester->sendTestEmail($settings, $admin);
            $this->addFlash('success', $translator->trans('flash.admin.smtp_test_sent', ['%email%' => $admin->getEmail()]));
        } catch (TransportExceptionInterface $exception) {
            $this->addFlash('error', $translator->trans('flash.admin.smtp_test_failed', ['%error%' => $exception->getMessage()]));
        }

        return $this->redirectToRoute('app_admin_smtp');
    }
}
