<?php

namespace App\Queries;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ClientNotificationQuery
{
    public function unreadCount(User $client): int
    {
        return Notification::query()
            ->where('user_id', $client->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginateForClient(User $client, int $perPage = 15): LengthAwarePaginator
    {
        return Notification::query()
            ->with([
                'motoCancellation:id,radicado,status,owner_user_id,holder_name',
                'creditCancellation:id,radicado,status,owner_user_id,holder_name',
            ])
            ->where('user_id', $client->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
