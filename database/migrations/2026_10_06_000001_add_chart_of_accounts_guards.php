<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;

/**
 * Chart-of-accounts guards at the database layer (valid parents, no cycles, locked meaning of used
 * accounts, same-company journal lines, no posting to group accounts). Re-syncing the database
 * objects creates the guard triggers on existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        // The guards are part of the database objects; they go with the objects migration's down().
    }
};
