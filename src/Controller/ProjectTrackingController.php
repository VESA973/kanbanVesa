<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ActivityLogRepository;
use App\Repository\TaskRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\ProjectProgress;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Owner-only views to follow who did what and what remains to be done.
 */
#[Route('/projects/{id}', requirements: ['id' => '\d+'])]
#[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
#[IsGranted(ProjectVoter::TRACK, 'project')]
final class ProjectTrackingController extends AbstractController
{
    #[Route('/progress', name: 'app_project_progress', methods: ['GET'])]
    public function progress(Project $project, ProjectProgress $projectProgress, TaskRepository $taskRepository, ClockInterface $clock): Response
    {
        return $this->render('project/progress.html.twig', [
            'project' => $project,
            'rows' => $projectProgress->byMember($project),
            'overdueTasks' => $taskRepository->findOverdue($project, $clock->now()),
        ]);
    }

    #[Route('/activity', name: 'app_project_activity', methods: ['GET'])]
    public function activity(Project $project, ActivityLogRepository $activityLogRepository, #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1): Response
    {
        return $this->render('project/activity.html.twig', [
            'project' => $project,
            'page' => $page,
        ] + $activityLogRepository->findPage($project, $page));
    }
}
