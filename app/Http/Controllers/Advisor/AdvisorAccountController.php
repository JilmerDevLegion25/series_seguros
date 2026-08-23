<?php

namespace App\Http\Controllers\Advisor;

use App\Actions\AdvisorAccounts\ResetAdvisorPasswordAction;
use App\Actions\AdvisorAccounts\UpdateAdvisorAccountAction;
use App\Actions\Auth\CreateAdvisorAction;
use App\DTOs\Auth\CreateAdvisorData;
use App\DTOs\Auth\UpdateAdvisorData;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Http\Requests\Advisor\StoreAdvisorAccountRequest;
use App\Http\Requests\Advisor\UpdateAdvisorAccountRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdvisorAccountController
{
    public function index(Request $request): View
    {
        $query = User::query()
            ->where('role_id', Role::idFor(RoleCode::ADVISOR));
        $sort = $this->normalizeSort($request->query('sort'));

        $this->applySort($query, $sort);

        return view('advisor.accounts.index', [
            'advisors' => $query->get(),
            'currentSort' => $sort,
        ]);
    }

    public function create(): View
    {
        return view('advisor.accounts.create');
    }

    public function store(
        StoreAdvisorAccountRequest $request,
        CreateAdvisorAction $createAdvisor,
    ): RedirectResponse {
        $createAdvisor->execute(new CreateAdvisorData(
            username: (string) $request->string('username'),
            name: (string) $request->string('name'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),
        ));

        return redirect()
            ->route('advisor.accounts.index')
            ->with('status', 'Advisor account created with a temporary password.');
    }

    public function edit(User $advisor): View
    {
        return view('advisor.accounts.edit', [
            'advisor' => $this->advisorOrFail($advisor),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(
        UpdateAdvisorAccountRequest $request,
        User $advisor,
        UpdateAdvisorAccountAction $updateAdvisorAccount,
    ): RedirectResponse {
        $updateAdvisorAccount->execute($this->advisorOrFail($advisor), new UpdateAdvisorData(
            name: (string) $request->string('name'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),
            status: UserStatus::from((string) $request->validated('status')),
        ));

        return redirect()
            ->route('advisor.accounts.index')
            ->with('status', 'Advisor account updated.');
    }

    public function resetPassword(
        User $advisor,
        ResetAdvisorPasswordAction $resetAdvisorPassword,
    ): RedirectResponse {
        $result = $resetAdvisorPassword->execute($this->advisorOrFail($advisor));

        return redirect()
            ->route('advisor.accounts.index')
            ->with('status', 'Advisor password reset with a temporary password.')
            ->with('temporary_password', $result['temporary_password'])
            ->with('temporary_password_advisor', $result['user']->username);
    }

    private function advisorOrFail(User $user): User
    {
        if ($user->role_id !== Role::idFor(RoleCode::ADVISOR)) {
            throw new NotFoundHttpException;
        }

        return $user;
    }

    private function normalizeSort(mixed $sort): string
    {
        $sort = is_scalar($sort) ? (string) $sort : '';

        return array_key_exists($sort, $this->sortOptions()) ? $sort : 'username_asc';
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(): array
    {
        return [
            'username_asc' => 'Usuario ascendente',
            'username_desc' => 'Usuario descendente',
            'name_asc' => 'Nombre ascendente',
            'name_desc' => 'Nombre descendente',
            'email_asc' => 'Email ascendente',
            'email_desc' => 'Email descendente',
            'phone_asc' => 'Telefono ascendente',
            'phone_desc' => 'Telefono descendente',
            'status_asc' => 'Estado ascendente',
            'status_desc' => 'Estado descendente',
        ];
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'username_desc' => $query->orderByDesc('username')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('username'),
            'name_desc' => $query->orderByDesc('name')->orderBy('username'),
            'email_asc' => $query->orderBy('email')->orderBy('username'),
            'email_desc' => $query->orderByDesc('email')->orderBy('username'),
            'phone_asc' => $query->orderBy('phone')->orderBy('username'),
            'phone_desc' => $query->orderByDesc('phone')->orderBy('username'),
            'status_asc' => $query->orderBy('status')->orderBy('username'),
            'status_desc' => $query->orderByDesc('status')->orderBy('username'),
            default => $query->orderBy('username')->orderBy('id'),
        };
    }
}
