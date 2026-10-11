<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every WhatsApp message the system sent (or received as a reply): who, what, which template, what Meta answered, and how far it got
 * (queued → sent → delivered → read, or failed with Meta's error code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_wa_company_fk')->restrictOnDelete();
            $table->string('direction', 3)->default('out'); // out | in
            $table->string('to_number', 20); // the other party's number: digits only, with the country code
            $table->string('type', 16)->default('text'); // text | template | inbound types (text, image, …)
            $table->string('template', 120)->nullable();
            $table->string('language', 12)->nullable();
            $table->text('body')->nullable(); // the text, or the template parameters as JSON
            $table->longText('payload')->nullable(); // the JSON sent to Meta
            $table->longText('response')->nullable(); // what Meta answered
            $table->string('wamid', 191)->nullable()->unique('acct_wa_wamid_unique'); // Meta's id of the message
            $table->string('status', 12)->default('queued'); // queued | sent | delivered | read | failed | received
            $table->string('error_code', 16)->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('mode', 8)->default('fake'); // fake | live
            $table->string('related_type', 120)->nullable(); // the record that caused it (a receipt, a fee …)
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_wa_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'acct_wa_status_idx');
            $table->index(['company_id', 'to_number'], 'acct_wa_number_idx');
            $table->index(['related_type', 'related_id'], 'acct_wa_related_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_whatsapp_messages');
    }
};
