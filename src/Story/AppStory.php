<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

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
        TaskFactory::new()->inColumn($todo)->many(4)->create();
        TaskFactory::new()->inColumn($doing)->many(2)->create();
        TaskFactory::new()->inColumn($done)->create(['title' => 'Choisir la nouvelle palette']);

        ProjectFactory::new()->withColumns('À faire', 'En cours', 'Terminé')->create(['name' => 'Application mobile', 'owner' => $demo, 'color' => ProjectColor::EMERALD]);
        ProjectFactory::new()
            ->withColumns('Idées', 'Validé')
            ->withMember($demo, ProjectRole::EDITOR)
            ->create(['name' => 'Salon professionnel 2027', 'owner' => $alex, 'color' => ProjectColor::AMBER]);
    }
}
