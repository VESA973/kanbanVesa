<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
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

        ProjectFactory::new()
            ->withMember($alex, ProjectRole::EDITOR)
            ->withMember($sam, ProjectRole::VIEWER)
            ->create(['name' => 'Refonte du site', 'owner' => $demo, 'color' => ProjectColor::INDIGO, 'description' => 'Nouvelle charte graphique et migration vers Symfony 7.4.']);
        ProjectFactory::createOne(['name' => 'Application mobile', 'owner' => $demo, 'color' => ProjectColor::EMERALD]);
        ProjectFactory::new()
            ->withMember($demo, ProjectRole::EDITOR)
            ->create(['name' => 'Salon professionnel 2027', 'owner' => $alex, 'color' => ProjectColor::AMBER]);
    }
}
