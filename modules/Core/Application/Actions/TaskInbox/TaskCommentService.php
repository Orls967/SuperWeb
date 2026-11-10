<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\CoreTaskComment;
use Modules\Core\Domain\Models\CoreTaskHistory;

/**
 * Service pengelolaan diskusi internal dan catatan kolaboratif pada tiket tugas kotak masuk.
 */
class TaskCommentService
{
    /**
     * Menambahkan komentar atau catatan kerja pada sebuah tugas.
     *
     * @param  array<string, string>|null  $attachments
     */
    public function addComment(CoreTask $task, User $author, string $comment, ?array $attachments = null): CoreTaskComment
    {
        $created = CoreTaskComment::create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'author_name' => $author->name,
            'comment' => trim($comment),
            'attachments' => $attachments,
        ]);

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => $author->id,
            'action' => 'comment_added',
            'from_status' => $task->status,
            'to_status' => $task->status,
            'notes' => 'Catatan ditambahkan oleh '.$author->name.': '.mb_substr($comment, 0, 50).'...',
        ]);

        return $created;
    }
}
