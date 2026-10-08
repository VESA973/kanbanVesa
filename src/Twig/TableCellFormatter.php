<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\TaskTableColumn;
use App\Enum\TableColumnType;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * How a stored cell value reads (page, CSV export) and how it is written back in its input.
 */
final readonly class TableCellFormatter
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFunction('table_cell_display')]
    public function display(TaskTableColumn $column, string|int|float|bool|null $value): string
    {
        if (null === $value) {
            return TableColumnType::CHECKBOX === $column->getType() ? $this->translator->trans('task_table.no') : '';
        }

        return match ($column->getType()) {
            TableColumnType::NUMBER => self::number($value),
            TableColumnType::DATE => (\DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value) ?: new \DateTimeImmutable())->format('d/m/Y'),
            TableColumnType::CHECKBOX => $this->translator->trans(true === $value ? 'task_table.yes' : 'task_table.no'),
            TableColumnType::MEMBER => $this->memberName($column, $value),
            TableColumnType::TEXT => (string) $value,
        };
    }

    /**
     * The value as it goes back in an editable input (numbers keep the French comma).
     */
    #[AsTwigFunction('table_cell_input')]
    public function input(TaskTableColumn $column, string|int|float|bool|null $value): string
    {
        return match (true) {
            null === $value, \is_bool($value) => '',
            TableColumnType::NUMBER === $column->getType() => str_replace('.', ',', (string) $value),
            default => (string) $value,
        };
    }

    #[AsTwigFunction('table_number')]
    public static function number(string|int|float|bool $value): string
    {
        $number = (float) $value;
        $decimals = floor($number) === $number ? 0 : min(4, \strlen(rtrim(substr(strrchr((string) $number, '.') ?: '.', 1), '0')));

        return number_format($number, $decimals, ',', "\u{202F}");
    }

    /**
     * A former member (removed since) keeps showing as "ancien membre".
     */
    private function memberName(TaskTableColumn $column, string|int|float|bool $value): string
    {
        foreach ($column->getTable()->getTask()->getProject()->getMembers() as $member) {
            if ($member->getUser()->getId() === (int) $value) {
                return $member->getUser()->getFullName();
            }
        }

        return $this->translator->trans('task_table.former_member');
    }
}
