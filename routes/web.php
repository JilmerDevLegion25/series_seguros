<?php

use App\Http\Controllers\Advisor\AdvisorAccountController;
use App\Http\Controllers\Advisor\CancellationExportController;
use App\Http\Controllers\Advisor\CancellationHistoryController;
use App\Http\Controllers\Advisor\CancellationWorkspaceController;
use App\Http\Controllers\Advisor\CreditCancellationController as AdvisorCreditCancellationController;
use App\Http\Controllers\Advisor\MotoCancellationController as AdvisorMotoCancellationController;
use App\Http\Controllers\Advisor\OperationalAuditController;
use App\Http\Controllers\Advisor\RadicadoSmsController;
use App\Http\Controllers\Advisor\ResponseImportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Client\CancellationController as ClientCancellationController;
use App\Http\Controllers\Client\NotificationController as ClientNotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Otp\OtpController;
use App\Http\Controllers\PublicPortal\CreditCancellationController as PublicCreditCancellationController;
use App\Http\Controllers\PublicPortal\MotoCancellationController as PublicMotoCancellationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::get('/password/recovery', [PasswordRecoveryController::class, 'create'])->name('password.recovery');
Route::post('/password/recovery', [PasswordRecoveryController::class, 'store'])->name('password.recovery.store');
Route::post('/password/recovery/reset', [PasswordRecoveryController::class, 'update'])->name('password.recovery.update');

Route::post('/otp/{publicReference}/resend', [OtpController::class, 'resend'])->name('otp.resend');
Route::post('/otp/{publicReference}/verify', [OtpController::class, 'verify'])->name('otp.verify');

Route::get('/moto', [PublicMotoCancellationController::class, 'create'])->name('public.moto.create');
Route::post('/moto', [PublicMotoCancellationController::class, 'store'])->name('public.moto.store');
Route::post('/moto/complete', [PublicMotoCancellationController::class, 'complete'])->name('public.moto.complete');
Route::get('/credit', [PublicCreditCancellationController::class, 'create'])->name('public.credit.create');
Route::post('/credit', [PublicCreditCancellationController::class, 'store'])->name('public.credit.store');
Route::post('/credit/complete', [PublicCreditCancellationController::class, 'complete'])->name('public.credit.complete');

Route::middleware(['auth', 'active', 'password.changed'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/advisor/cancellations', [CancellationWorkspaceController::class, 'index'])
        ->middleware('can:cancellations.view')
        ->name('advisor.cancellations.index');
    Route::get('/advisor/cancellations/export', [CancellationExportController::class, 'create'])
        ->middleware('can:cancellations.export')
        ->name('advisor.cancellations.export.create');
    Route::post('/advisor/cancellations/export', [CancellationExportController::class, 'store'])
        ->middleware('can:cancellations.export')
        ->name('advisor.cancellations.export');
    Route::get('/advisor/operational/audit', [OperationalAuditController::class, 'index'])
        ->middleware('can:cancellations.activity.view')
        ->name('advisor.operational.audit.index');

    Route::prefix('client')
        ->name('client.')
        ->group(function (): void {
            Route::get('/cancellations', [ClientCancellationController::class, 'index'])
                ->name('cancellations.index');
            Route::get('/cancellations/moto/{moto}', [ClientCancellationController::class, 'showMoto'])
                ->name('cancellations.moto.show');
            Route::get('/cancellations/credit/{credit}', [ClientCancellationController::class, 'showCredit'])
                ->name('cancellations.credit.show');
            Route::get('/notifications', [ClientNotificationController::class, 'index'])
                ->name('notifications.index');
            Route::post('/notifications/{notification}/read', [ClientNotificationController::class, 'markRead'])
                ->name('notifications.read');
        });

    Route::prefix('advisor/accounts')
        ->name('advisor.accounts.')
        ->group(function (): void {
            Route::get('/', [AdvisorAccountController::class, 'index'])
                ->middleware('can:advisor_accounts.view')
                ->name('index');
            Route::get('/create', [AdvisorAccountController::class, 'create'])
                ->middleware('can:advisor_accounts.create')
                ->name('create');
            Route::post('/', [AdvisorAccountController::class, 'store'])
                ->middleware('can:advisor_accounts.create')
                ->name('store');
            Route::get('/{advisor}/edit', [AdvisorAccountController::class, 'edit'])
                ->middleware('can:advisor_accounts.update')
                ->name('edit');
            Route::patch('/{advisor}', [AdvisorAccountController::class, 'update'])
                ->middleware('can:advisor_accounts.update')
                ->name('update');
            Route::post('/{advisor}/reset-password', [AdvisorAccountController::class, 'resetPassword'])
                ->middleware('can:advisor_accounts.reset_password')
                ->name('reset-password');
        });

    Route::prefix('advisor/responses/import')
        ->name('advisor.responses.import.')
        ->middleware('can:responses.import')
        ->group(function (): void {
            Route::get('/', [ResponseImportController::class, 'create'])
                ->name('create');
            Route::get('/template', [ResponseImportController::class, 'template'])
                ->name('template');
            Route::post('/', [ResponseImportController::class, 'store'])
                ->name('store');
        });

    Route::prefix('advisor/moto')
        ->name('advisor.moto.')
        ->group(function (): void {
            Route::get('/create', [AdvisorMotoCancellationController::class, 'create'])
                ->middleware('can:cancellations.create')
                ->name('create');
            Route::post('/', [AdvisorMotoCancellationController::class, 'store'])
                ->middleware('can:cancellations.create')
                ->name('store');
            Route::post('/complete', [AdvisorMotoCancellationController::class, 'complete'])
                ->middleware('can:cancellations.create')
                ->name('complete');
            Route::get('/{moto}/edit', [AdvisorMotoCancellationController::class, 'edit'])
                ->middleware('can:cancellations.update')
                ->name('edit');
            Route::get('/{moto}/activity', [CancellationHistoryController::class, 'moto'])
                ->middleware('can:cancellations.activity.view')
                ->name('activity');
            Route::patch('/{moto}', [AdvisorMotoCancellationController::class, 'update'])
                ->middleware('can:cancellations.update')
                ->name('update');
            Route::get('/{moto}/reassign', [AdvisorMotoCancellationController::class, 'reassign'])
                ->middleware('can:cancellations.reassign')
                ->name('reassign');
            Route::post('/{moto}/reassign', [AdvisorMotoCancellationController::class, 'storeReassignment'])
                ->middleware('can:cancellations.reassign')
                ->name('reassign.store');
            Route::post('/{moto}/radicado', [AdvisorMotoCancellationController::class, 'generateRadicado'])
                ->middleware('can:cancellations.update')
                ->name('radicado.generate');
            Route::post('/{moto}/sms/retry', [RadicadoSmsController::class, 'retryMoto'])
                ->middleware('can:radicado_sms.retry')
                ->name('sms.retry');
        });

    Route::prefix('advisor/credit')
        ->name('advisor.credit.')
        ->group(function (): void {
            Route::get('/create', [AdvisorCreditCancellationController::class, 'create'])
                ->middleware('can:cancellations.create')
                ->name('create');
            Route::post('/', [AdvisorCreditCancellationController::class, 'store'])
                ->middleware('can:cancellations.create')
                ->name('store');
            Route::post('/complete', [AdvisorCreditCancellationController::class, 'complete'])
                ->middleware('can:cancellations.create')
                ->name('complete');
            Route::get('/{credit}/edit', [AdvisorCreditCancellationController::class, 'edit'])
                ->middleware('can:cancellations.update')
                ->name('edit');
            Route::get('/{credit}/activity', [CancellationHistoryController::class, 'credit'])
                ->middleware('can:cancellations.activity.view')
                ->name('activity');
            Route::patch('/{credit}', [AdvisorCreditCancellationController::class, 'update'])
                ->middleware('can:cancellations.update')
                ->name('update');
            Route::get('/{credit}/reassign', [AdvisorCreditCancellationController::class, 'reassign'])
                ->middleware('can:cancellations.reassign')
                ->name('reassign');
            Route::post('/{credit}/reassign', [AdvisorCreditCancellationController::class, 'storeReassignment'])
                ->middleware('can:cancellations.reassign')
                ->name('reassign.store');
            Route::post('/{credit}/sms/retry', [RadicadoSmsController::class, 'retryCredit'])
                ->middleware('can:radicado_sms.retry')
                ->name('sms.retry');
        });
});
