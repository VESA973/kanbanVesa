<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\ChecklistItem;
use App\Entity\Comment;
use App\Entity\Label;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Enum\TaskPriority;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\save;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        $demo = UserFactory::new()->verified()->create(['email' => 'demo@kanban.lan', 'firstName' => 'Camille', 'lastName' => 'Martin']);
        [$alex, $sam] = UserFactory::new()->verified()->many(2)->create();

        $agency = ProgramFactory::createOne(['name' => 'Agence web 2027', 'owner' => $demo, 'color' => ProjectColor::VIOLET, 'description' => 'Tous les chantiers numériques de l\'année.']);

        $website = ProjectFactory::new()
            ->inProgram($agency)
            ->withColumns('À faire', 'En cours', 'Terminé')
            ->withMember($alex, ProjectRole::EDITOR)
            ->withMember($sam, ProjectRole::VIEWER)
            ->create(['name' => 'Refonte du site', 'owner' => $demo, 'color' => ProjectColor::INDIGO, 'description' => 'Nouvelle charte graphique et migration vers Symfony 7.4.']);
        [$todo, $doing, $done] = $website->getColumns()->toArray();
        TaskFactory::new()->inColumn($todo)->assignedTo($demo)->create(['title' => 'Rédiger les mentions légales', 'dueDate' => force(new \DateTimeImmutable('-2 days midnight')), 'priority' => force(TaskPriority::HIGH)]);
        TaskFactory::new()->inColumn($todo)->assignedTo($demo)->create(['title' => 'Préparer la démo client', 'dueDate' => force(new \DateTimeImmutable('today'))]);
        TaskFactory::new()->inColumn($todo)->assignedTo($alex)->create(['title' => 'Optimiser les images', 'dueDate' => force(new \DateTimeImmutable('+5 days midnight'))]);
        TaskFactory::new()->inColumn($todo)->create(['title' => 'Vérifier l\'accessibilité', 'priority' => force(TaskPriority::URGENT)]);
        TaskFactory::new()->inColumn($doing)->assignedTo($alex)->create(['title' => 'Intégrer la page d\'accueil', 'dueDate' => force(new \DateTimeImmutable('-1 day midnight'))]);
        TaskFactory::new()->inColumn($doing)->assignedTo($demo)->create(['title' => 'Migrer le blog']);
        TaskFactory::new()->inColumn($done)->assignedTo($sam)->create(['title' => 'Choisir la nouvelle palette', 'completedAt' => force(new \DateTimeImmutable('-1 day'))]);

        $this->decorate($website, $demo, $alex);

        ProjectFactory::new()->inProgram($agency)->withColumns('À faire', 'En cours', 'Terminé')->create(['name' => 'Application mobile', 'owner' => $demo, 'color' => ProjectColor::EMERALD]);
        ProjectFactory::new()
            ->withColumns('Idées', 'Validé')
            ->withMember($demo, ProjectRole::EDITOR)
            ->create(['name' => 'Salon professionnel 2027', 'owner' => $alex, 'color' => ProjectColor::AMBER]);
    }

    /**
     * Labels, a checklist and a discussion so every board feature is visible in the demo.
     */
    private function decorate(Project $website, User $demo, User $alex): void
    {
        $tasks = [];
        foreach ($website->getColumns() as $column) {
            array_push($tasks, ...$column->getTasks()->getValues());
        }
        [$legal, $demoTask, , $accessibility, $homepage] = $tasks;

        $bug = new Label($website, 'Bug', ProjectColor::ROSE);
        $design = new Label($website, 'Design', ProjectColor::VIOLET);
        $content = new Label($website, 'Contenu', ProjectColor::SKY);
        array_map(save(...), [$bug, $design, $content]);
        $legal->replaceLabels([$content]);
        $accessibility->replaceLabels([$bug, $design]);
        $homepage->replaceLabels([$design]);

        foreach (['Maquette validée', 'Intégration mobile', 'Recette'] as $position => $item) {
            $checklistItem = new ChecklistItem($homepage, $item, $position);
            if (0 === $position) {
                $checklistItem->toggle();
            }
            $homepage->getChecklistItems()->add($checklistItem);
            save($checklistItem);
        }

        save(new Comment($demoTask, $alex, 'Je peux préparer les slides si besoin.'));
        save(new Comment($demoTask, $demo, 'Merci ! Je m’occupe de la démo technique.'));
        save($website);
    }
}
