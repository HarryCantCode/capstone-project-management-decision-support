<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ProjectCreated Event
 *
 * Broadcasted when a new project is successfully created.
 * Used to trigger real-time UI updates (e.g., via WebSockets).
 */
class ProjectCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $html The rendered HTML for the new project row
     * @param array<string, int> $stats Current project statistics (total, pending, etc.)
     */
    public function __construct(
        public string $html,
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
