<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskAgenda;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MyTasksController extends AbstractController
{
    #[Route('/my-tasks', name: 'app_my_tasks', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, TaskRepository $taskRepository, ClockInterface $clock): Response
    {
        return $this->render('my_tasks/index.html.twig', [
            'groups' => TaskAgenda::group($taskRepository->findAssignedTo($user), $clock->now()),
        ]);
    }
}
