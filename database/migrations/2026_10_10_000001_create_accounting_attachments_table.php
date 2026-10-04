<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting documents attached to accounting records (journal entries today). The file lives on a private
 * disk; sha256 lets the package warn when the same file is attached to two entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_attachments', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_attachments_company_fk')->restrictOnDelete();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('description', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained($users, indexName: 'acct_attachments_uploaded_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'acct_attachments_attachable_idx');
            $table->index(['company_id', 'sha256'], 'acct_attachments_company_sha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_attachments');
    }
};
