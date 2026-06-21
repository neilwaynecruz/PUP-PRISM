<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryRealtimeMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $modules
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $entity,
        public string $action,
        public string $title,
        public string $message,
        public array $roles,
        public array $modules,
        public ?string $url = null,
        public array $context = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (string $role): PrivateChannel => new PrivateChannel("inventory.role.{$role}"),
            $this->roles,
        );
    }

    public function broadcastAs(): string
    {
        return 'inventory.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'entity' => $this->entity,
            'action' => $this->action,
            'title' => $this->title,
            'message' => $this->message,
            'modules' => $this->modules,
            'url' => $this->url,
            'context' => $this->context,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
