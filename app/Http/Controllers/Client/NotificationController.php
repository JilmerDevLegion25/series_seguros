<?php

namespace App\Http\Controllers\Client;

use App\Actions\Notifications\MarkNotificationReadAction;
use App\Models\Notification;
use App\Models\User;
use App\Queries\ClientNotificationQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController
{
    public function index(Request $request, ClientNotificationQuery $notifications): View
    {
        $client = $this->client($request->user());

        return view('client.notifications.index', [
            'notifications' => $notifications->paginateForClient($client),
            'unreadCount' => $notifications->unreadCount($client),
        ]);
    }

    public function markRead(
        Request $request,
        Notification $notification,
        MarkNotificationReadAction $markNotificationRead,
    ): RedirectResponse {
        $client = $this->client($request->user());

        if ($notification->user_id !== $client->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $markNotificationRead->execute($notification);

        return redirect()
            ->route('client.notifications.index')
            ->with('status', 'Notificacion marcada como leida.');
    }

    private function client(?User $user): User
    {
        if (! $user instanceof User || ! $user->isClient()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
