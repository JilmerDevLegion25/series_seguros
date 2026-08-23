<?php

namespace App\Http\Controllers\Advisor;

use App\Actions\Exports\GenerateCancellationExportAction;
use App\Exceptions\CancellationExportException;
use App\Http\Requests\Advisor\ExportCancellationRequest;
use App\Models\User;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CancellationExportController
{
    public function create(): View
    {
        return view('advisor.cancellations.export');
    }

    /**
     * @throws AuthorizationException
     */
    public function store(
        ExportCancellationRequest $request,
        GenerateCancellationExportAction $generateCancellationExport,
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): BinaryFileResponse|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        $filters = $request->toFilters(
            $identityNormalizer,
            $phoneNormalizer,
            $plateNormalizer,
            $creditNumberNormalizer,
        );

        try {
            $result = $generateCancellationExport->execute(
                $filters,
                $user,
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (CancellationExportException $exception) {
            return back()
                ->withInput($request->except('_token'))
                ->withErrors(['export' => $exception->getMessage()]);
        }

        $response = response()->download(
            $result->absolutePath,
            $result->filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
            ],
        );
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
