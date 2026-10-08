<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\EmailLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminEmailLogController extends AbstractController
{
    private const int LIMIT = 100;

    #[Route('/admin/emails', name: 'app_admin_emails', methods: ['GET'])]
    public function index(EmailLogRepository $repository): Response
    {
        return $this->render('admin/emails.html.twig', [
            'logs' => $repository->findLatest(self::LIMIT),
            'limit' => self::LIMIT,
        ]);
    }
}
