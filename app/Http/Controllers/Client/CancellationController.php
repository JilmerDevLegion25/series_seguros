<?php

namespace App\Http\Controllers\Client;

use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\User;
use App\Queries\ClientCancellationListQuery;
use App\Queries\ClientNotificationQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CancellationController
{
    public function index(
        Request $request,
        ClientCancellationListQuery $cancellations,
        ClientNotificationQuery $notifications,
    ): View {
        $client = $this->client($request->user());
        $sort = $request->string('sort')->toString();

        return view('client.cancellations.index', [
            'cancellations' => $cancellations->paginateForClient($client, sort: $sort)->withQueryString(),
            'unreadCount' => $notifications->unreadCount($client),
        ]);
    }

    public function showMoto(
        Request $request,
        MotoCancellation $moto,
        ClientNotificationQuery $notifications,
    ): View {
        $client = $this->client($request->user());
        $this->assertOwnsMoto($client, $moto);

        return view('client.cancellations.moto-show', [
            'moto' => $moto->load('response'),
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
            'unreadCount' => $notifications->unreadCount($client),
        ]);
    }

    public function showCredit(
        Request $request,
        CreditCancellation $credit,
        ClientNotificationQuery $notifications,
    ): View {
        $client = $this->client($request->user());
        $this->assertOwnsCredit($client, $credit);

        return view('client.cancellations.credit-show', [
            'credit' => $credit->load('response'),
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
            'unreadCount' => $notifications->unreadCount($client),
        ]);
    }

    private function client(?User $user): User
    {
        if (! $user instanceof User || ! $user->isClient()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $user;
    }

    private function assertOwnsMoto(User $client, MotoCancellation $moto): void
    {
        if ($moto->owner_user_id !== $client->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    private function assertOwnsCredit(User $client, CreditCancellation $credit): void
    {
        if ($credit->owner_user_id !== $client->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
