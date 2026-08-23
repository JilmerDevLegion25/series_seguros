<?php

namespace App\Http\Controllers\Advisor;

use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Http\Requests\Advisor\SearchCancellationRequest;
use App\Models\Role;
use App\Models\User;
use App\Queries\AdvisorCancellationSearchQuery;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CancellationWorkspaceController
{
    /**
     * @throws AuthorizationException
     */
    public function index(
        SearchCancellationRequest $request,
        AdvisorCancellationSearchQuery $search,
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): View {
        $advisor = $this->advisor($request->user());
        $filters = $request->toFilters($identityNormalizer, $phoneNormalizer, $plateNormalizer, $creditNumberNormalizer);
        $results = $search
            ->paginateForAdvisor($advisor, $filters, $request->perPage())
            ->withQueryString();
        $permissions = $this->permissionSet($advisor);

        return view('advisor.cancellations.index', [
            'results' => $results,
            'advisors' => $this->activeAdvisors(),
            'typeOptions' => SearchCancellationRequest::typeOptions(),
            'statusOptions' => SearchCancellationRequest::statusOptions(),
            'sortOptions' => SearchCancellationRequest::sortOptions(),
            'canUpdate' => isset($permissions[PermissionKey::CANCELLATIONS_UPDATE->value]),
            'canReassign' => isset($permissions[PermissionKey::CANCELLATIONS_REASSIGN->value]),
            'canRetrySms' => isset($permissions[PermissionKey::RADICADO_SMS_RETRY->value]),
            'canImportResponses' => isset($permissions[PermissionKey::RESPONSES_IMPORT->value]),
            'canExport' => isset($permissions[PermissionKey::CANCELLATIONS_EXPORT->value]),
            'canActivity' => isset($permissions[PermissionKey::CANCELLATIONS_ACTIVITY_VIEW->value]),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function advisor(?User $user): User
    {
        if (
            ! $user instanceof User
            || ! $user->isAdvisor()
        ) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * @return Collection<int, User>
     */
    private function activeAdvisors()
    {
        return User::query()
            ->where('role_id', Role::idFor(RoleCode::ADVISOR))
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    /**
     * @return array<string, true>
     */
    private function permissionSet(User $advisor): array
    {
        $keys = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $advisor->role_id)
            ->pluck('permissions.key')
            ->all();

        $permissions = [];

        foreach ($keys as $key) {
            if (is_string($key)) {
                $permissions[$key] = true;
            }
        }

        return $permissions;
    }
}
