<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        UserFactory::new()->verified()->create(['email' => 'demo@kanban.lan', 'firstName' => 'Camille', 'lastName' => 'Martin']);
        UserFactory::new()->verified()->many(4)->create();
    }
}
