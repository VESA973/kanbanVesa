<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Security\Voter\CommentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class CommentVoterTest extends TestCase
{
    public function testAuthorAndOwnerCanDeleteOthersCannot(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $owner);
        $author = new User('author@example.com', 'Alex', 'Author');
        $editor = new User('editor@example.com', 'Eddie', 'Editor');
        $project->addMember($author, ProjectRole::VIEWER);
        $project->addMember($editor, ProjectRole::EDITOR);
        $comment = new Comment(new Task($project->addColumn('À faire'), 'Tâche', 0, $owner), $author, 'Bonjour');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($author, $comment));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $comment), 'The owner moderates.');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($editor, $comment));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new User('x@example.com', 'X', 'Stranger'), $comment));
    }

    private function vote(User $user, Comment $comment): int
    {
        return new CommentVoter()->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $comment, [CommentVoter::DELETE]);
    }
}
