<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Voucher type rules shared by the API, React and Blade screens.
 */
class VoucherTypeService
{
    public function __construct(private readonly VoucherNumberService $numbers) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(?VoucherType $type = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', CompanyRule::unique('accounting_voucher_types', 'code')->ignore($type?->id)],
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/_-]+$/'],
            'format' => ['required', 'string', 'max:60'],
            'reset' => ['required', Rule::in(VoucherType::resets())],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input, ?VoucherType $type = null): array
    {
        $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));
        $input['format'] = trim((string) ($input['format'] ?? '')) ?: VoucherType::DEFAULT_FORMAT;
        $input['reset'] = $input['reset'] ?? VoucherType::RESET_YEARLY;

        $validator = Validator::make($input, $this->rules($type));
        $validator->after(function ($validator) use ($input): void {
            if ($problem = VoucherNumberService::formatProblem($input['format'], (string) $input['reset'])) {
                $validator->errors()->add('format', $problem);
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $data  validated data
     */
    public function create(array $data): VoucherType
    {
        return VoucherType::query()->create([...$data, 'is_active' => $data['is_active'] ?? true]);
    }

    /**
     * @param  array<string, mixed>  $data  validated data
     */
    public function update(VoucherType $type, array $data): VoucherType
    {
        if ($this->isUsed($type)) {
            // Numbers already issued keep their meaning: the series and its identity stay fixed.
            foreach (['code' => 'code', 'reset' => 'numbering reset'] as $field => $label) {
                if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $type->{$field}) {
                    throw new AccountingException("Voucher type {$type->code} has numbered entries: its {$label} cannot change.");
                }
            }
        }

        if ($type->is_system && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw new AccountingException("{$type->code} is the default voucher type and cannot be deactivated.");
        }

        $type->update($data);

        return $type->refresh();
    }

    public function delete(VoucherType $type): void
    {
        if ($type->is_system) {
            throw new AccountingException("{$type->code} is the default voucher type and cannot be deleted.");
        }

        // Any entry with the type, drafts and soft-deleted ones included (the foreign key would refuse anyway).
        if (DB::table('accounting_journal_entries')->where('voucher_type_id', $type->id)->exists()) {
            throw new AccountingException("Voucher type {$type->code} is used by journal entries: deactivate it instead.");
        }

        DB::transaction(function () use ($type): void {
            $type->sequences()->delete();
            $type->delete();
        });
    }

    public function isUsed(VoucherType $type): bool
    {
        return DB::table('accounting_journal_entries')->where('voucher_type_id', $type->id)->whereNotNull('voucher_number')->exists();
    }

    /**
     * A voucher type with its next number and usage, for lists and forms.
     *
     * @return array<string, mixed>
     */
    public function present(VoucherType $type): array
    {
        return [
            ...$type->only(['id', 'code', 'name', 'prefix', 'format', 'reset', 'description', 'is_active', 'is_system']),
            'next_number' => $this->numbers->preview($type),
            'entries_count' => DB::table('accounting_journal_entries')->where('voucher_type_id', $type->id)->whereNotNull('voucher_number')->count(),
        ];
    }
}
