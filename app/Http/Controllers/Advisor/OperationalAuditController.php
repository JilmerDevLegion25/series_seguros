<?php

namespace App\Http\Controllers\Advisor;

use App\Models\User;
use App\Queries\OperationalAuditQuery;
use App\Support\History\HistoryMetadataFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final readonly class OperationalAuditController
{
    public function __construct(
        private HistoryMetadataFormatter $formatter,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function index(Request $request, OperationalAuditQuery $audits): View
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return view('advisor.audit.index', [
            'audits' => $audits->paginateForAdvisor($user)->withQueryString(),
            'formatter' => $this->formatter,
        ]);
    }
}
