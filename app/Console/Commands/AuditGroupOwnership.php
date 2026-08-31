<?php

namespace App\Console\Commands;

use App\Models\ChatRoom;
use Illuminate\Console\Command;

class AuditGroupOwnership extends Command
{
    protected $signature = 'chat:audit-group-owners';

    protected $description = 'Read-only ownership audit; does not promote users or change groups';

    public function handle(): int
    {
        $rows = [];
        $unresolved = false;
        foreach (ChatRoom::withTrashed()->where('type', 'group')->with('roomMembers')->cursor() as $room) {
            $owners = $room->roomMembers->where('role', 'owner')->pluck('user_id');
            $status = $owners->count() === 1 ? 'owner recorded' : ($owners->isEmpty() ? 'owner missing' : 'multiple owners: review required');
            $unresolved = $unresolved || $owners->count() !== 1;
            $rows[] = [$room->id, $room->created_by ?? '-', $owners->implode(', ') ?: '-', $status];
        }
        $this->table(['Room ID', 'Creator ID (historical)', 'Owner membership IDs (users)', 'Status'], $rows);

        return $unresolved ? self::FAILURE : self::SUCCESS;
    }
}
