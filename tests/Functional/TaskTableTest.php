<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskTable;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\TaskTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

use function Zenstruck\Foundry\Persistence\save;

final class TaskTableTest extends FunctionalTestCase
{
    public function testAnEditorBuildsATeamTableAndFillsItInline(): void
    {
        $client = self::createClient();
        [$project, $task] = $this->task();
        $client->loginUser($this->member($project, ProjectRole::EDITOR));

        $crawler = $client->request('GET', '/tasks/'.$task->getId());
        $client->submit($crawler->filter('form[action$="/tables"]')->form(['title' => 'Équipe chantier', 'template' => 'team']));
        $crawler = $client->followRedirect();

        self::assertSelectorTextContains('main table thead', 'Nom');
        self::assertSelectorTextContains('main table thead', 'Disponible');
        $table = $this->table();
        [$name, , , , $available] = $table->getColumns()->getValues();
        $row = $table->getRows()->first() ?: throw new \LogicException();
        $token = $this->token($crawler);

        $this->patch($client, '/task-table-rows/'.$row->getId().'/cells/'.$name->getId(), ['value' => '  Marie Dupont '], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Marie Dupont', $this->json($client)['input'] ?? null);

        $this->patch($client, '/task-table-rows/'.$row->getId().'/cells/'.$available->getId(), ['value' => '1'], $token);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/tasks/'.$task->getId());
        self::assertSelectorExists('input[value="Marie Dupont"]');
        self::assertSelectorExists('input[type="checkbox"][checked]');
    }

    public function testAWrongValueIsRefusedWithItsReasonAndNumbersAreTotalled(): void
    {
        $client = self::createClient();
        [$project, $task] = $this->task();
        $client->loginUser($project->getOwner());
        $crawler = $client->request('GET', '/tasks/'.$task->getId());
        $client->submit($crawler->filter('form[action$="/tables"]')->form(['title' => 'Outils', 'template' => 'tools']));
        $crawler = $client->followRedirect();
        $quantity = $this->table()->getColumns()->get(1) ?? throw new \LogicException();
        $row = $this->table()->getRows()->first() ?: throw new \LogicException();

        $this->patch($client, '/task-table-rows/'.$row->getId().'/cells/'.$quantity->getId(), ['value' => 'deux'], $this->token($crawler));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Cette valeur ne correspond pas au type de la colonne.', $this->json($client)['error'] ?? null);

        $this->patch($client, '/task-table-rows/'.$row->getId().'/cells/'.$quantity->getId(), ['value' => '2,5'], $this->token($crawler));
        self::assertSame(['input' => '2,5', 'total' => '2,5'], $this->json($client));
    }

    public function testAViewerReadsAndExportsButCannotEdit(): void
    {
        $client = self::createClient();
        [$project, $task] = $this->task();
        $viewer = $this->member($project, ProjectRole::VIEWER);
        $client->loginUser($project->getOwner());
        $crawler = $client->request('GET', '/tasks/'.$task->getId());
        $client->submit($crawler->filter('form[action$="/tables"]')->form(['title' => 'Outils', 'template' => 'tools']));
        $crawler = $client->followRedirect();
        $table = $this->table();
        $tool = $table->getColumns()->first() ?: throw new \LogicException();
        $row = $table->getRows()->first() ?: throw new \LogicException();
        $this->patch($client, '/task-table-rows/'.$row->getId().'/cells/'.$tool->getId(), ['value' => 'Perceuse'], $this->token($crawler));

        $client->loginUser($viewer);
        $crawler = $client->request('GET', '/tasks/'.$task->getId());
        self::assertSelectorTextContains('main table tbody', 'Perceuse');
        self::assertCount(0, $crawler->filter('main table input, form[action*="/task-table"]'));

        $client->request('GET', '/task-tables/'.$table->getId().'/export.csv');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=outils.csv');
        self::assertStringContainsString("Perceuse;;;Non\n", (string) $client->getResponse()->getContent());

        $client->request('POST', '/task-tables/'.$table->getId().'/rows');
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertCount(1, $this->table()->getRows());
    }

    public function testANonMemberGetsA404(): void
    {
        $client = self::createClient();
        [$project, $task] = $this->task();
        $client->loginUser($project->getOwner());
        $crawler = $client->request('GET', '/tasks/'.$task->getId());
        $client->submit($crawler->filter('form[action$="/tables"]')->form(['title' => 'Outils', 'template' => 'empty']));

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/task-tables/'.$this->table()->getId().'/export.csv');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array{Project, Task}
     */
    private function task(): array
    {
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => UserFactory::createOne()]);
        $column = $project->getColumns()->first() ?: throw new \LogicException();

        return [$project, TaskFactory::new()->inColumn($column)->create(['title' => 'Préparer le chantier'])];
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        $project->addMember($user, $role);
        save($project);

        return $user;
    }

    private function table(): TaskTable
    {
        // A fresh read: the request may have changed the table since it was last loaded.
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(TaskTableRepository::class)->findOneBy([]) ?? throw new \LogicException('No table was created.');
    }

    private function token(Crawler $crawler): string
    {
        return $crawler->filter('[data-table-cell-csrf-token-value]')->first()->attr('data-table-cell-csrf-token-value') ?? throw new \LogicException();
    }

    /**
     * @return array<string, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, string> $payload
     */
    private function patch(KernelBrowser $client, string $url, array $payload, string $csrfToken): void
    {
        $client->request('PATCH', $url, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
