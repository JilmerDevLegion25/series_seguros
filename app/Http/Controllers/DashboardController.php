<?php

namespace App\Http\Controllers;

use App\Enums\CancellationStatus;
use App\Enums\PermissionKey;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\User;
use App\Queries\ClientNotificationQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DashboardController
{
    public function __invoke(Request $request, ClientNotificationQuery $notifications): View
    {
        $user = $request->user();

        if ($user instanceof User && $user->isClient()) {
            return view('client.dashboard', [
                'clientStats' => $this->clientStats($user),
                'unreadCount' => $notifications->unreadCount($user),
            ]);
        }

        return view('dashboard', [
            'advisorStats' => $user instanceof User && $user->isAdvisor() && $user->hasPermission(PermissionKey::CANCELLATIONS_VIEW)
                ? $this->advisorStats()
                : null,
        ]);
    }

    /**
     * @return array{total: int, moto: int, credit: int, en_gestion: int, respuesta_obtenida: int}
     */
    private function advisorStats(): array
    {
        $motoTotal = MotoCancellation::query()->count();
        $creditTotal = CreditCancellation::query()->count();
        $motoInProgress = MotoCancellation::query()->where('status', CancellationStatus::EN_GESTION->value)->count();
        $creditInProgress = CreditCancellation::query()->where('status', CancellationStatus::EN_GESTION->value)->count();

        return [
            'total' => $motoTotal + $creditTotal,
            'moto' => $motoTotal,
            'credit' => $creditTotal,
            'en_gestion' => $motoInProgress + $creditInProgress,
            'respuesta_obtenida' => ($motoTotal - $motoInProgress) + ($creditTotal - $creditInProgress),
        ];
    }

    /**
     * @return array{total: int, moto: int, credit: int, en_gestion: int, respuesta_obtenida: int}
     */
    private function clientStats(User $client): array
    {
        $motoTotal = MotoCancellation::query()->where('owner_user_id', $client->id)->count();
        $creditTotal = CreditCancellation::query()->where('owner_user_id', $client->id)->count();
        $motoInProgress = MotoCancellation::query()
            ->where('owner_user_id', $client->id)
            ->where('status', CancellationStatus::EN_GESTION->value)
            ->count();
        $creditInProgress = CreditCancellation::query()
            ->where('owner_user_id', $client->id)
            ->where('status', CancellationStatus::EN_GESTION->value)
            ->count();

        return [
            'total' => $motoTotal + $creditTotal,
            'moto' => $motoTotal,
            'credit' => $creditTotal,
            'en_gestion' => $motoInProgress + $creditInProgress,
            'respuesta_obtenida' => ($motoTotal - $motoInProgress) + ($creditTotal - $creditInProgress),
        ];
    }
}
