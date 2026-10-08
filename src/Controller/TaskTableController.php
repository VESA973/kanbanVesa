<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\CellPayload;
use App\Controller\Payload\StepPayload;
use App\Controller\Payload\TableColumnPayload;
use App\Controller\Payload\TaskTablePayload;
use App\Entity\Task;
use App\Entity\TaskTable;
use App\Entity\TaskTableColumn;
use App\Entity\TaskTableRow;
use App\Enum\TableColumnType;
use App\Exception\TaskTableException;
use App\Security\Voter\TaskVoter;
use App\Service\TaskTableCsvExporter;
use App\Service\TaskTableManager;
use App\Twig\TableCellFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tables inside a task. Editing needs TASK_EDIT; viewers only read and export.
 */
final class TaskTableController extends AbstractController
{
    public function __construct(
        private readonly TaskTableManager $tableManager,
    ) {
    }

    #[Route('/tasks/{id}/tables', name: 'app_task_table_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, 'task')]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["task"].getId()'))]
    public function new(Task $task, #[MapRequestPayload] TaskTablePayload $payload): Response
    {
        $this->tableManager->create($task, $payload->title, $payload->template);

        return $this->backToTask($task);
    }

    #[Route('/task-tables/{id}/rename', name: 'app_task_table_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["table"].getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["table"].getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["table"].getTask().getId()'))]
    public function rename(TaskTable $table, #[MapRequestPayload] TaskTablePayload $payload): Response
    {
        $this->tableManager->rename($table, $payload->title);

        return $this->backToTask($table->getTask());
    }

    #[Route('/task-tables/{id}/delete', name: 'app_task_table_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["table"].getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["table"].getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["table"].getTask().getId()'))]
    public function delete(TaskTable $table): Response
    {
        $task = $table->getTask();
        $this->tableManager->delete($table);

        return $this->backToTask($task);
    }

    #[Route('/task-tables/{id}/columns', name: 'app_task_table_column_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["table"].getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["table"].getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["table"].getTask().getId()'))]
    public function addColumn(TaskTable $table, #[MapRequestPayload] TableColumnPayload $payload): Response
    {
        $this->attempt(fn (): TaskTableColumn => $this->tableManager->addColumn($table, $payload->name, $payload->type));

        return $this->backToTask($table->getTask());
    }

    #[Route('/task-table-columns/{id}/rename', name: 'app_task_table_column_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["column"].getTable().getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["column"].getTable().getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["column"].getTable().getTask().getId()'))]
    public function renameColumn(TaskTableColumn $column, #[MapRequestPayload] TableColumnPayload $payload): Response
    {
        $this->tableManager->renameColumn($column, $payload->name);

        return $this->backToTask($column->getTable()->getTask());
    }

    #[Route('/task-table-columns/{id}/move', name: 'app_task_table_column_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["column"].getTable().getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["column"].getTable().getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["column"].getTable().getTask().getId()'))]
    public function moveColumn(TaskTableColumn $column, #[MapRequestPayload] StepPayload $payload): Response
    {
        $this->tableManager->moveColumn($column, $payload->step);

        return $this->backToTask($column->getTable()->getTask());
    }

    #[Route('/task-table-columns/{id}/delete', name: 'app_task_table_column_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["column"].getTable().getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["column"].getTable().getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["column"].getTable().getTask().getId()'))]
    public function deleteColumn(TaskTableColumn $column): Response
    {
        $task = $column->getTable()->getTask();
        $this->attempt(fn () => $this->tableManager->deleteColumn($column));

        return $this->backToTask($task);
    }

    #[Route('/task-tables/{id}/rows', name: 'app_task_table_row_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["table"].getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["table"].getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["table"].getTask().getId()'))]
    public function addRow(TaskTable $table): Response
    {
        $this->attempt(fn (): TaskTableRow => $this->tableManager->addRow($table));

        return $this->backToTask($table->getTask());
    }

    #[Route('/task-table-rows/{id}/delete', name: 'app_task_table_row_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["row"].getTable().getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["row"].getTable().getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["row"].getTable().getTask().getId()'))]
    public function deleteRow(TaskTableRow $row): Response
    {
        $task = $row->getTable()->getTask();
        $this->tableManager->deleteRow($row);

        return $this->backToTask($task);
    }

    /**
     * Inline edition (table_cell_controller.js): answers the stored value, or 422 with the reason.
     */
    #[Route('/task-table-rows/{id}/cells/{column}', name: 'app_task_table_cell', requirements: ['id' => '\d+', 'column' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["row"].getTable().getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["row"].getTable().getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["row"].getTable().getTask().getId()'), tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function updateCell(TaskTableRow $row, TaskTableColumn $column, #[MapRequestPayload] CellPayload $payload, TableCellFormatter $formatter, TranslatorInterface $translator): JsonResponse
    {
        try {
            $value = $this->tableManager->updateCell($row, $column, $payload->value);
        } catch (TaskTableException $exception) {
            return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\InvalidArgumentException) {
            throw new UnprocessableEntityHttpException('Unknown column for this row.');
        }

        return $this->json([
            'input' => $formatter->input($column, $value),
            'total' => TableColumnType::NUMBER === $column->getType() ? TableCellFormatter::number($row->getTable()->totalOf($column)) : null,
        ]);
    }

    #[Route('/task-tables/{id}/export.csv', name: 'app_task_table_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["table"].getTask()'), statusCode: 404)]
    public function export(TaskTable $table, TaskTableCsvExporter $exporter): Response
    {
        $filename = new AsciiSlugger('fr')->slug($table->getTitle())->lower()->toString() ?: 'tableau';

        return new Response($exporter->export($table), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename.'.csv'),
        ]);
    }

    /**
     * Limits (columns, rows, last column) are shown as a flash message.
     */
    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (TaskTableException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }
    }

    private function backToTask(Task $task): Response
    {
        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}
