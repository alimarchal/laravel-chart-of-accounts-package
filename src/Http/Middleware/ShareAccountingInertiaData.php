<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Middleware;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\AccountingPermissions;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares what the React pages need under the "accounting" prop, independent of the host app's
 * HandleInertiaRequests middleware:
 *   accounting.permissions  { "journal-entries.post": true, … } for the current user
 *   accounting.flash        { success, error } from the session
 */
class ShareAccountingInertiaData
{
    /**
     * @return array<string, mixed>
     */
    private function company(Request $request): array
    {
        $companies = app(CurrentCompany::class);
        $present = fn (Company $company): array => ['id' => (int) $company->getKey(), 'code' => $company->code, 'name' => $company->name];

        return [
            'enabled' => CurrentCompany::enabled(),
            'current' => $present($companies->get()),
            'list' => $companies->accessibleBy($request->user())->map($present)->values()->all(),
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (class_exists(Inertia::class)) {
            Inertia::share('accounting', fn () => [
                'permissions' => $this->permissions($request),
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ],
                'company' => $this->company($request),
                'approvals' => [
                    'enabled' => (bool) config('accounting.approvals.enabled', false),
                    'threshold' => (string) config('accounting.approvals.threshold', '0'),
                ],
            ]);
        }

        return $next($request);
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        return collect(AccountingPermissions::all())
            ->mapWithKeys(fn (string $permission) => [$permission => $user->can($permission)])
            ->filter()
            ->all();
    }
}
