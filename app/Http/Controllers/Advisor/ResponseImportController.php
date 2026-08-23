<?php

namespace App\Http\Controllers\Advisor;

use App\Actions\Imports\ProcessResponseImportAction;
use App\DTOs\Imports\ResponseImportData;
use App\Enums\CancellationType;
use App\Http\Requests\Advisor\ImportResponseRequest;
use App\Models\User;
use App\Services\Spreadsheet\CancellationExportWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ResponseImportController
{
    private const RESPONSE_TEMPLATE_HEADERS = [
        'Radicado',
        'Placa',
        'Nombre',
        'Cedula',
        'Fecha cancelacion',
        'Observaciones',
    ];

    public function create(): View
    {
        return view('advisor.imports.responses', [
            'batch' => null,
            'types' => $this->types(),
        ]);
    }

    public function template(CancellationExportWriter $writer): BinaryFileResponse
    {
        $diskName = (string) config('imports.disk');
        $storage = Storage::disk($diskName);
        $filename = now()->format('YmdHis').'-'.bin2hex(random_bytes(8)).'-response-import-template.xlsx';
        $storedPath = 'templates/'.$filename;

        $storage->makeDirectory('templates');
        $absolutePath = $storage->path($storedPath);
        $writer->writeSheets($absolutePath, [
            'Respuestas' => [
                self::RESPONSE_TEMPLATE_HEADERS,
            ],
        ]);

        $response = response()->download(
            $absolutePath,
            'plantilla-importacion-respuestas.xlsx',
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

    /**
     * @throws AuthorizationException
     */
    public function store(ImportResponseRequest $request, ProcessResponseImportAction $processResponseImport): View
    {
        $uploadedFile = $request->file('file');

        if (! $uploadedFile instanceof UploadedFile) {
            throw new AuthorizationException;
        }

        $storedPath = $this->storeUploadedFile($uploadedFile);
        $result = $processResponseImport->execute(
            new ResponseImportData($request->cancellationType(), $storedPath),
            $this->advisor($request->user()),
            (string) $request->attributes->get('request_id', ''),
        );

        return view('advisor.imports.responses', [
            'batch' => $result->batch->load('rowResults'),
            'types' => $this->types(),
        ]);
    }

    private function storeUploadedFile(UploadedFile $uploadedFile): string
    {
        $filename = now()->format('YmdHis').'-'.bin2hex(random_bytes(16)).'.xlsx';
        $storedPath = Storage::disk((string) config('imports.disk'))->putFileAs(
            'responses',
            $uploadedFile,
            $filename,
        );

        return $storedPath;
    }

    /**
     * @throws AuthorizationException
     */
    private function advisor(?User $user): User
    {
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
    {
        return [
            CancellationType::MOTO->value => 'Moto',
            CancellationType::CREDIT->value => 'Credit',
        ];
    }
}
