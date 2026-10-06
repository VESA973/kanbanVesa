<?php

declare(strict_types=1);

namespace App\Enum;

enum ActivityAction: string
{
    case PROJECT_CREATED = 'project_created';
    case COLUMN_CREATED = 'column_created';
    case COLUMN_RENAMED = 'column_renamed';
    case COLUMN_DELETED = 'column_deleted';
    case TASK_CREATED = 'task_created';
    case TASK_UPDATED = 'task_updated';
    case TASK_MOVED = 'task_moved';
    case TASK_ASSIGNED = 'task_assigned';
    case TASK_UNASSIGNED = 'task_unassigned';
    case TASK_COMPLETED = 'task_completed';
    case TASK_REOPENED = 'task_reopened';
    case TASK_DELETED = 'task_deleted';
    case INVITATION_SENT = 'invitation_sent';
    case MEMBER_JOINED = 'member_joined';
    case MEMBER_ROLE_CHANGED = 'member_role_changed';
    case MEMBER_REMOVED = 'member_removed';

    public function translationKey(): string
    {
        return 'activity.action.'.$this->value;
    }
}
