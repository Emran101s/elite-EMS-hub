<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices — the document you actually send to be paid.
 *
 * The book already knows what is scheduled (event_contract_payments) and what
 * has arrived (their paid_cents). Neither is an invoice: a schedule is an
 * agreement about the future, and an invoice is a demand issued on a date with
 * a number on it, which is the thing a client's accounts department pays
 * against and the thing an auditor asks to see.
 *
 * An invoice therefore keeps its own lines and its own money rather than
 * pointing at an installment and calling that enough. `payment_id` on a line
 * records where it CAME from, so raising an invoice off a schedule stays one
 * click, but editing the line afterwards cannot rewrite the agreement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // An invoice usually belongs to an event, but not always — a
            // retainer or a deposit can be raised before one exists.
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('event_contracts')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number')->unique();

            // Only the issuing lifecycle is stored. Whether it is paid, part
            // paid or overdue is derived from money and dates, the same rule
            // EventContractPayment follows — a document must not be able to
            // claim it is settled while the cash is missing.
            $table->string('status')->default('draft');   // draft · sent · void

            $table->string('currency', 3)->default('JOD');
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();

            $table->float('tax_pct')->default(0);
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->date('paid_at')->nullable();

            // Who it is addressed to, frozen at issue. A client can be renamed
            // or move address; an invoice already sent must not change.
            $table->text('bill_to')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();

            $table->index(['status', 'due_on']);
            $table->index('event_id');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Provenance, not a link the totals depend on: the line keeps its
            // own numbers so amending it never edits the contract's schedule.
            $table->foreignId('payment_id')->nullable()
                ->constrained('event_contract_payments')->nullOnDelete();

            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->bigInteger('unit_cents')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
