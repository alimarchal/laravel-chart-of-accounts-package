<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Branches of the current company: create, change, remove, and choose who works in which. A user with no branch assigned works in all of
 * them; one with branches works in those only (see Support\CurrentBranch).
 */
class BranchService
{
    /**
     * @return array<string, mixed>
     */
    public function rules(?Branch $branch = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', CompanyRule::unique('accounting_branches', 'code')->ignore($branch?->getKey())],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_head_office' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $data['code'] = strtoupper((string) $data['code']);
            $branch = Branch::query()->create($data);
            $this->onlyOneHeadOffice($branch);
            AccountingAuditLog::record($branch, 'BRANCH_CREATED', null, $branch->only(['code', 'name']));

            return $branch;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function update(Branch $branch, array $data): Branch
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        return DB::transaction(function () use ($branch, $data): Branch {
            $before = $branch->only(array_keys($data));
            $branch->update($data);
            $this->onlyOneHeadOffice($branch);
            AccountingAuditLog::record($branch, 'BRANCH_UPDATED', $before, $branch->only(array_keys($data)));

            return $branch;
        });
    }

    public function delete(Branch $branch): void
    {
        if ($this->isUsed($branch)) {
            throw new AccountingException('This branch has entries, invoices, bills or payments: deactivate it instead of deleting it.');
        }

        DB::transaction(function () use ($branch): void {
            AccountingAuditLog::record($branch, 'BRANCH_DELETED', $branch->only(['code', 'name']), null);
            $branch->delete();
        });
    }

    public function isUsed(Branch $branch): bool
    {
        foreach (['accounting_journal_entries', 'accounting_party_documents', 'accounting_party_payments'] as $table) {
            if (DB::table($table)->where('branch_id', $branch->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Users working in the branch.
     *
     * @return Collection<int, \stdClass>
     */
    public function members(Branch $branch): Collection
    {
        $users = config('accounting.users_table', 'users');

        return DB::table('accounting_branch_user as bu')
            ->join("{$users} as u", 'u.id', '=', 'bu.user_id')
            ->where('bu.branch_id', $branch->getKey())
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email']);
    }

    public function grant(Branch $branch, Authenticatable $user): void
    {
        DB::table('accounting_branch_user')->updateOrInsert(
            ['branch_id' => $branch->getKey(), 'user_id' => $user->getAuthIdentifier()],
            ['updated_at' => now(), 'created_at' => now()],
        );
        CurrentBranch::flush();
        AccountingAuditLog::record($branch, 'BRANCH_ACCESS_GRANTED', null, ['user_id' => $user->getAuthIdentifier(), 'email' => $user instanceof Model ? $user->getAttribute('email') : null]);
    }

    public function revoke(Branch $branch, Authenticatable $user): void
    {
        DB::table('accounting_branch_user')->where('branch_id', $branch->getKey())->where('user_id', $user->getAuthIdentifier())->delete();
        CurrentBranch::flush();
        AccountingAuditLog::record($branch, 'BRANCH_ACCESS_REVOKED', null, ['user_id' => $user->getAuthIdentifier(), 'email' => $user instanceof Model ? $user->getAttribute('email') : null]);
    }

    /** Only one branch of a company is the head office. */
    private function onlyOneHeadOffice(Branch $branch): void
    {
        if ($branch->is_head_office) {
            Branch::query()->whereKeyNot($branch->getKey())->where('is_head_office', true)->update(['is_head_office' => false]);
        }
    }
}
