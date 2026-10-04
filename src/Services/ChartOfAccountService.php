<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for creating, updating, deleting and reading the chart of accounts.
 *
 * Integrity rules enforced here (shared by the API, Inertia and Blade controllers):
 *  - the hierarchy cannot contain cycles; a parent must be a group account of the same account type;
 *  - an account with journal lines cannot change its code, type, normal balance, or become a group;
 *  - a group account with children cannot become a posting account;
 *  - accounts referenced by config('accounting.defaults') cannot be renamed (code), deactivated, or deleted;
 *  - accounts with children or journal lines cannot be deleted (deactivate them instead).
 */
class ChartOfAccountService
{
    /**
     * @return array<string, mixed>
     */
    public function rules(?ChartOfAccount $record = null): array
    {
        return [
            'parent_id' => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')],
            'account_type_id' => ['required', Rule::exists('accounting_account_types', 'id')],
            'currency_id' => ['required', Rule::exists('accounting_currencies', 'id')],
            'account_code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_chart_of_accounts', 'account_code')->ignore($record?->id)],
            'account_name' => ['required', 'string', 'max:255'],
            // Optional: defaults to the account type's normal balance. May differ for contra accounts.
            'normal_balance' => ['nullable', Rule::in(['debit', 'credit'])],
            'description' => ['nullable', 'string'],
            'is_group' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validate a request into model attributes. Boolean flags that are absent from the request
     * are left unchanged on update (and default to is_group=false / is_active=true on create).
     *
     * @return array<string, mixed>
     */
    public function validated(Request $request, ?ChartOfAccount $record = null): array
    {
        foreach (['is_group', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $request->merge([$flag => $request->boolean($flag)]);
            }
        }

        $data = $request->validate($this->rules($record));

        if (empty($data['normal_balance'])) {
            $data['normal_balance'] = $record && (int) $record->account_type_id === (int) $data['account_type_id']
                ? $record->normal_balance
                : AccountType::query()->whereKey($data['account_type_id'])->value('normal_balance');
        }

        if (! $record) {
            $data['is_group'] ??= false;
            $data['is_active'] ??= true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($data): ChartOfAccount {
            $this->assertValid($data);

            return ChartOfAccount::query()->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($account, $data): ChartOfAccount {
            $account = ChartOfAccount::query()->lockForUpdate()->findOrFail($account->id);
            $this->assertValid($data, $account);

            $account->update($data);

            return $account->refresh();
        });
    }

    public function delete(ChartOfAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $account = ChartOfAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($account->is_system) {
                throw new AccountingException('System accounts cannot be deleted.');
            }

            if ($this->isReferencedByConfig($account->account_code)) {
                throw new AccountingException("Account {$account->account_code} is referenced by config('accounting.defaults') and cannot be deleted.");
            }

            if ($account->children()->exists()) {
                throw new AccountingException('Accounts with child accounts cannot be deleted. Move or delete the children first.');
            }

            if ($account->journalEntryLines()->exists()) {
                throw new AccountingException('Accounts with journal entries cannot be deleted. Deactivate the account instead.');
            }

            $account->delete();
        });
    }

    /**
     * Load the whole chart in one query and assemble the tree in memory (no N+1 per level).
     *
     * @return Collection<int, ChartOfAccount>
     */
    public function tree(): Collection
    {
        /** @var Collection<int, ChartOfAccount> $accounts */
        $accounts = ChartOfAccount::query()->with('accountType')->orderBy('account_code')->get();
        $byParent = $accounts->groupBy(fn (ChartOfAccount $account) => $account->parent_id ?? 0);

        $attach = function (ChartOfAccount $account) use (&$attach, $byParent): ChartOfAccount {
            $children = new Collection($byParent->get($account->id, collect())->all());

            foreach ($children as $child) {
                /** @var ChartOfAccount $child */
                $attach($child);
            }

            $account->setRelation('childrenRecursive', $children);

            return $account;
        };

        return new Collection($byParent->get(0, collect())->map($attach)->values()->all());
    }

    /**
     * IDs of the given account codes plus all their descendants.
     *
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    public function idsWithDescendants(array $codes): array
    {
        $ids = ChartOfAccount::query()->whereIn('account_code', array_filter($codes))->pluck('id')->all();
        $frontier = $ids;

        while ($frontier !== []) {
            $frontier = ChartOfAccount::query()->whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValid(array $data, ?ChartOfAccount $account = null): void
    {
        $this->assertParentIsValid($data, $account);

        if (! $account) {
            return;
        }

        $hasLines = $account->journalEntryLines()->exists();

        if ($hasLines) {
            foreach (['account_code' => 'code', 'account_type_id' => 'account type', 'normal_balance' => 'normal balance'] as $field => $label) {
                if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $account->{$field}) {
                    throw new AccountingException("The {$label} of an account with journal entries cannot be changed.");
                }
            }

            if (($data['is_group'] ?? false) && ! $account->is_group) {
                throw new AccountingException('An account with journal entries cannot be converted into a group account.');
            }
        }

        if (array_key_exists('is_group', $data) && ! $data['is_group'] && $account->is_group && $account->children()->exists()) {
            throw new AccountingException('A group account with child accounts cannot be converted into a posting account.');
        }

        if ($this->isReferencedByConfig($account->account_code)) {
            if (isset($data['account_code']) && $data['account_code'] !== $account->account_code) {
                throw new AccountingException("Account {$account->account_code} is referenced by config('accounting.defaults'); its code cannot be changed.");
            }

            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                throw new AccountingException("Account {$account->account_code} is referenced by config('accounting.defaults') and cannot be deactivated.");
            }

            if (array_key_exists('is_group', $data) && (bool) $data['is_group'] !== (bool) $account->is_group) {
                throw new AccountingException("Account {$account->account_code} is referenced by config('accounting.defaults'); its group flag cannot be changed.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertParentIsValid(array $data, ?ChartOfAccount $account): void
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId === null || $parentId === '') {
            return;
        }

        $parentId = (int) $parentId;

        if ($account && $parentId === (int) $account->id) {
            throw new AccountingException('An account cannot be its own parent.');
        }

        $parent = ChartOfAccount::query()->findOrFail($parentId);

        if (! $parent->is_group) {
            throw new AccountingException('The parent account must be a group account.');
        }

        $typeId = $data['account_type_id'] ?? $account?->account_type_id;

        if ($typeId !== null && (int) $parent->account_type_id !== (int) $typeId) {
            throw new AccountingException('A child account must have the same account type as its parent.');
        }

        if ($account) {
            // Walk up from the new parent; reaching this account means the move would create a cycle.
            $cursor = $parent;
            $guard = 0;

            while ($cursor !== null && $guard++ < 1000) {
                if ((int) $cursor->id === (int) $account->id) {
                    throw new AccountingException('An account cannot be moved under one of its own descendants.');
                }

                $cursor = $cursor->parent_id ? ChartOfAccount::query()->find($cursor->parent_id) : null;
            }
        }
    }

    private function isReferencedByConfig(string $code): bool
    {
        $codes = collect((array) config('accounting.defaults', []))
            ->filter(fn ($value, $key) => str_ends_with((string) $key, '_account_code') && is_scalar($value))
            ->map(fn ($value) => (string) $value);

        return $codes->contains($code);
    }
}
