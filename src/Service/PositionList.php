<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Positionable;

/**
 * Keeps positions contiguous (0, 1, 2…) whenever an element is inserted or removed.
 */
final class PositionList
{
    /**
     * Inserts $element at $position (clamped to the list bounds) and renumbers the list.
     *
     * @template T of Positionable
     *
     * @param iterable<T> $elements the list, without $element
     * @param T           $element
     *
     * @return int the position actually given to $element
     */
    public static function insert(iterable $elements, Positionable $element, int $position): int
    {
        $list = self::without($elements, $element);
        $position = max(0, min($position, \count($list)));
        array_splice($list, $position, 0, [$element]);
        self::renumber($list);

        return $position;
    }

    /**
     * @param iterable<Positionable> $elements
     */
    public static function remove(iterable $elements, Positionable $element): void
    {
        self::renumber(self::without($elements, $element));
    }

    /**
     * @param iterable<Positionable> $elements
     *
     * @return list<Positionable>
     */
    private static function without(iterable $elements, Positionable $element): array
    {
        $list = [];
        foreach ($elements as $candidate) {
            if ($candidate !== $element) {
                $list[] = $candidate;
            }
        }

        return $list;
    }

    /**
     * @param list<Positionable> $elements
     */
    private static function renumber(array $elements): void
    {
        foreach ($elements as $position => $element) {
            $element->setPosition($position);
        }
    }
}
