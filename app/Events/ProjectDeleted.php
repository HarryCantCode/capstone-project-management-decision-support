<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ProjectDeleted Event
 *
 * Broadcasted when a project is deleted (soft or hard).
 * Used to trigger real-time removal of the project from active client views.
 */
class ProjectDeleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param int $id The ID of the deleted project
     * @param array<string, int> $stats Updated project statistics after deletion
     */
    public function __construct(
        public int $id,
        public array $stats,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('projects'),
        ];
    }
}
