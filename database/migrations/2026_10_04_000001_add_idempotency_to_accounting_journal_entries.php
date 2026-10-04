<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->after('reference');
            $table->string('idempotency_hash', 64)->nullable()->after('idempotency_key');

            $table->unique('idempotency_key', 'acct_journals_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropUnique('acct_journals_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'idempotency_hash']);
        });
    }
};
