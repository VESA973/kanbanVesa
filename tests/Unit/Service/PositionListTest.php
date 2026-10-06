<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Positionable;
use App\Service\PositionList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionListTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, list<string>}>
     */
    public static function insertions(): iterable
    {
        yield 'at the top' => [0, 0, ['new', 'a', 'b', 'c']];
        yield 'in the middle' => [1, 1, ['a', 'new', 'b', 'c']];
        yield 'at the bottom' => [3, 3, ['a', 'b', 'c', 'new']];
        yield 'beyond the end is clamped' => [99, 3, ['a', 'b', 'c', 'new']];
        yield 'negative is clamped' => [-5, 0, ['new', 'a', 'b', 'c']];
    }

    /**
     * @param list<string> $expectedOrder
     */
    #[DataProvider('insertions')]
    public function testInsertRenumbersTheWholeList(int $requested, int $expected, array $expectedOrder): void
    {
        $list = $this->items('a', 'b', 'c');
        $new = $this->item('new', 42);

        self::assertSame($expected, PositionList::insert($list, $new, $requested));
        self::assertSame($expectedOrder, $this->order([...$list, $new]));
    }

    public function testInsertIgnoresTheElementIfAlreadyInTheList(): void
    {
        $list = $this->items('a', 'b', 'c');

        self::assertSame(2, PositionList::insert($list, $list[0], 2));
        self::assertSame(['b', 'c', 'a'], $this->order($list));
    }

    public function testRemoveClosesTheGap(): void
    {
        $list = $this->items('a', 'b', 'c');

        PositionList::remove($list, $list[1]);

        self::assertSame(0, $list[0]->position);
        self::assertSame(1, $list[2]->position);
    }

    /**
     * @return list<object{name: string, position: int}&Positionable>
     */
    private function items(string ...$names): array
    {
        return array_map($this->item(...), array_values($names), array_keys(array_values($names)));
    }

    /**
     * @return object{name: string, position: int}&Positionable
     */
    private function item(string $name, int $position): Positionable
    {
        return new class($name, $position) implements Positionable {
            public function __construct(public string $name, public int $position)
            {
            }

            public function setPosition(int $position): void
            {
                $this->position = $position;
            }
        };
    }

    /**
     * @param list<object{name: string, position: int}&Positionable> $items
     *
     * @return list<string>
     */
    private function order(array $items): array
    {
        usort($items, static fn (object $a, object $b): int => $a->position <=> $b->position);

        return array_map(static fn (object $item): string => $item->name, $items);
    }
}
