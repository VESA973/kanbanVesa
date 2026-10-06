<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Enum\TaskPriority;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\force;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        $demo = UserFactory::new()->verified()->create(['email' => 'demo@kanban.lan', 'firstName' => 'Camille', 'lastName' => 'Martin']);
        [$alex, $sam] = UserFactory::new()->verified()->many(2)->create();

        $website = ProjectFactory::new()
            ->withColumns('À faire', 'En cours', 'Terminé')
            ->withMember($alex, ProjectRole::EDITOR)
            ->withMember($sam, ProjectRole::VIEWER)
            ->create(['name' => 'Refonte du site', 'owner' => $demo, 'color' => ProjectColor::INDIGO, 'description' => 'Nouvelle charte graphique et migration vers Symfony 7.4.']);
        [$todo, $doing, $done] = $website->getColumns()->toArray();
        TaskFactory::new()->inColumn($todo)->create(['title' => 'Rédiger les mentions légales', 'assignee' => force($demo), 'dueDate' => force(new \DateTimeImmutable('-2 days midnight')), 'priority' => force(TaskPriority::HIGH)]);
        TaskFactory::new()->inColumn($todo)->create(['title' => 'Préparer la démo client', 'assignee' => force($demo), 'dueDate' => force(new \DateTimeImmutable('today'))]);
        TaskFactory::new()->inColumn($todo)->create(['title' => 'Optimiser les images', 'assignee' => force($alex), 'dueDate' => force(new \DateTimeImmutable('+5 days midnight'))]);
        TaskFactory::new()->inColumn($todo)->create(['title' => 'Vérifier l\'accessibilité', 'priority' => force(TaskPriority::URGENT)]);
        TaskFactory::new()->inColumn($doing)->create(['title' => 'Intégrer la page d\'accueil', 'assignee' => force($alex), 'dueDate' => force(new \DateTimeImmutable('-1 day midnight'))]);
        TaskFactory::new()->inColumn($doing)->create(['title' => 'Migrer le blog', 'assignee' => force($demo)]);
        TaskFactory::new()->inColumn($done)->create(['title' => 'Choisir la nouvelle palette', 'assignee' => force($sam), 'completedAt' => force(new \DateTimeImmutable('-1 day'))]);

        ProjectFactory::new()->withColumns('À faire', 'En cours', 'Terminé')->create(['name' => 'Application mobile', 'owner' => $demo, 'color' => ProjectColor::EMERALD]);
        ProjectFactory::new()
            ->withColumns('Idées', 'Validé')
            ->withMember($demo, ProjectRole::EDITOR)
            ->create(['name' => 'Salon professionnel 2027', 'owner' => $alex, 'color' => ProjectColor::AMBER]);
    }
}
