<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SearchController extends AbstractController
{
    private const int MIN_LENGTH = 2;

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, ProjectRepository $projectRepository, TaskRepository $taskRepository, #[MapQueryParameter] string $q = ''): Response
    {
        $term = mb_substr(trim($q), 0, 100);
        $searched = mb_strlen($term) >= self::MIN_LENGTH;

        return $this->render('search/index.html.twig', [
            'term' => $term,
            'searched' => $searched,
            'projects' => $searched ? $projectRepository->search($user, $term) : [],
            'tasks' => $searched ? $taskRepository->search($user, $term) : [],
        ]);
    }
}
