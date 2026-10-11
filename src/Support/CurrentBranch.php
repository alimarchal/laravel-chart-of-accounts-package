<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The branch of the company that the current request works in. Off (accounting.branches.enabled) nothing happens at all.
 *
 * Two different things, kept apart on purpose:
 *   access     a user assigned to branches works in those only; a user with no assignment (and a super-admin) in all of them.
 *              This is enforced on every read and write (BranchScope), whatever the user selected.
 *   selection  the "working branch": the web UI keeps it in the session, the API takes it from the X-Branch header (id or code).
 *              It sets the branch of what you create and limits lists and reports to that branch; none selected = all your branches.
 */
final class CurrentBranch
{
    public const SESSION_KEY = 'accounting.branch_id';

    /** @var array<string, array<int, int>|null> */
    private static array $access = [];

    private static ?Branch $explicit = null;

    private static bool $hasExplicit = false;

    public static function enabled(): bool
    {
        return (bool) config('accounting.branches.enabled', false);
    }

    public static function flush(): void
    {
        self::$access = [];
        self::$explicit = null;
        self::$hasExplicit = false;
    }

    /** Work in a branch (or in all, null) regardless of the request: commands, jobs, tests. */
    public static function set(?Branch $branch): void
    {
        self::$explicit = $branch;
        self::$hasExplicit = true;
    }

    /**
     * The branch ids the user may work in, or null for all of the company's branches.
     *
     * @return array<int, int>|null
     */
    public static function accessibleIds(?Authenticatable $user = null): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $user ??= auth()->user();

        if ($user === null || self::unrestricted($user)) {
            return null;
        }

        $key = $user->getAuthIdentifier().'|'.app(CurrentCompany::class)->id();

        return self::$access[$key] ??= (function () use ($user): ?array {
            $ids = DB::table('accounting_branch_user as bu')
                ->join('accounting_branches as b', 'b.id', '=', 'bu.branch_id')
                ->where('bu.user_id', $user->getAuthIdentifier())
                ->where('b.company_id', app(CurrentCompany::class)->id())
                ->pluck('b.id')->map(fn ($id) => (int) $id)->all();

            return $ids === [] ? null : $ids;                       // nothing assigned = every branch
        })();
    }

    /** Super-admins and admins (accounting.branches.unrestricted_roles) work in every branch whatever is assigned to them. */
    public static function unrestricted(Authenticatable $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole((array) config('accounting.branches.unrestricted_roles', ['super-admin', 'admin']));
    }

    /** A restricted user: assigned to some branches only. */
    public static function restricted(?Authenticatable $user = null): bool
    {
        return self::accessibleIds($user) !== null;
    }

    public static function canAccess(?Authenticatable $user, ?Branch $branch): bool
    {
        if (! self::enabled() || $branch === null) {
            return true;
        }

        $ids = self::accessibleIds($user);

        return $ids === null || in_array((int) $branch->getKey(), $ids, true);
    }

    /**
     * The branch chosen by the request (header or session), or null. Does not check access; unknown branches of this company are an error.
     */
    public static function requested(Request $request): ?Branch
    {
        $header = $request->header((string) config('accounting.branches.header', 'X-Branch'));

        if (is_string($header) && $header !== '') {
            return self::find($header) ?? throw new AccountingException("Unknown branch \"{$header}\".");
        }

        if ($request->hasSession() && $request->session()->has(self::SESSION_KEY)) {
            return Branch::query()->find($request->session()->get(self::SESSION_KEY));
        }

        return null;
    }

    public static function find(string|int $idOrCode): ?Branch
    {
        return Branch::query()
            ->where(fn ($query) => $query->where('code', (string) $idOrCode)->when(ctype_digit((string) $idOrCode), fn ($q) => $q->orWhere('id', (int) $idOrCode)))
            ->first();
    }

    /** The working branch, or null (all the user's branches). A branch the user may not use, or an inactive one, is not selected. */
    public static function selected(): ?Branch
    {
        if (! self::enabled()) {
            return null;
        }

        if (self::$hasExplicit) {
            return self::$explicit;
        }

        if (! app()->bound('request') || request()->route() === null) {
            return null;
        }

        $branch = self::requested(request());

        return $branch !== null && $branch->is_active && self::canAccess(request()->user(), $branch) ? $branch : null;
    }

    public static function selectedId(): ?int
    {
        $branch = self::selected();

        return $branch === null ? null : (int) $branch->getKey();
    }

    /**
     * The branch a new entry, invoice or payment is booked to when none is given: the working branch, or the only branch of a user who
     * works in exactly one. Otherwise null (head office / not allocated).
     */
    public static function defaultId(): ?int
    {
        if (! self::enabled()) {
            return null;
        }

        $selected = self::selectedId();

        if ($selected !== null) {
            return $selected;
        }

        $ids = self::accessibleIds();

        return $ids !== null && count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * The branch ids a report, list or total is limited to: the working branch, otherwise the user's branches, otherwise null = all.
     *
     * @return array<int, int>|null
     */
    public static function reportIds(): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $selected = self::selectedId();

        return $selected !== null ? [$selected] : self::accessibleIds();
    }

    /** Limit a query to reportIds() on the given (qualified) branch column. */
    public static function constrain(BuilderContract $query, string $column): BuilderContract
    {
        $ids = self::reportIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * The branch a record is booked to, checked: empty = company-wide (head office / not allocated). It must be an active branch of this
     * company that the user works in. With branches off nothing is stored.
     */
    public static function resolve(mixed $branch): ?int
    {
        if (! self::enabled()) {
            return null;
        }

        if ($branch === null || $branch === '') {
            // A user who works in some branches only cannot book to "no branch": they could not see it afterwards.
            if (self::restricted()) {
                throw new AccountingException('Choose the branch this belongs to.');
            }

            return null;
        }

        $found = $branch instanceof Branch ? $branch : Branch::query()->find($branch);

        if ($found === null || ! $found->is_active) {
            throw new AccountingException('The branch does not exist in the current company or is not active.');
        }

        if (! self::canAccess(auth()->user(), $found)) {
            throw new AccountingException('You do not have access to this branch.');
        }

        return (int) $found->getKey();
    }

    /**
     * The branches the user can pick in a form or a switcher (active, and only those the user works in): [] with branches off.
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    public static function options(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $ids = self::accessibleIds();

        return Branch::query()
            ->where('is_active', true)
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->orderByDesc('is_head_office')->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Branch $branch): array => ['id' => (int) $branch->id, 'code' => $branch->code, 'name' => $branch->name])
            ->all();
    }

    /**
     * reportIds() as an SQL condition for hand-written queries: " AND col IN (1, 2)", or "" for all branches. Integers only.
     */
    public static function sql(string $column): string
    {
        $ids = self::reportIds();

        return $ids === null ? '' : ($ids === [] ? ' AND 1 = 0' : " AND {$column} IN (".implode(', ', array_map('intval', $ids)).')');
    }
}
