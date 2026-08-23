<?php

namespace App\Http\Controllers\Advisor;

use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\User;
use App\Queries\AdvisorCancellationHistoryQuery;
use App\Support\History\HistoryMetadataFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final readonly class CancellationHistoryController
{
    public function __construct(
        private HistoryMetadataFormatter $formatter,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function moto(Request $request, MotoCancellation $moto, AdvisorCancellationHistoryQuery $history): View
    {
        $advisor = $this->advisor($request);

        return view('advisor.cancellations.history', [
            'product' => 'Moto',
            'radicado' => $moto->radicado,
            'backRoute' => route('advisor.moto.edit', $moto),
            'activities' => $history->motoActivities($advisor, $moto)->withQueryString(),
            'audits' => $history->motoAudits($advisor, $moto)->withQueryString(),
            'formatter' => $this->formatter,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function credit(Request $request, CreditCancellation $credit, AdvisorCancellationHistoryQuery $history): View
    {
        $advisor = $this->advisor($request);

        return view('advisor.cancellations.history', [
            'product' => 'Credit',
            'radicado' => $credit->radicado,
            'backRoute' => route('advisor.credit.edit', $credit),
            'activities' => $history->creditActivities($advisor, $credit)->withQueryString(),
            'audits' => $history->creditAudits($advisor, $credit)->withQueryString(),
            'formatter' => $this->formatter,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function advisor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
