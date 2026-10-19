<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_invoices', function (Blueprint $table) {
            $table->id();
            // Het getekende GoHighLevel-document; uniek, zodat een herhaalde
            // webhook nooit een tweede factuur maakt.
            $table->string('source_id')->unique();
            $table->string('opportunity_id')->nullable();
            $table->string('project')->nullable();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_vat')->nullable();
            $table->decimal('deal_amount_excl', 12, 2);
            $table->decimal('amount_excl', 12, 2);
            $table->unsignedBigInteger('billit_order_id')->nullable();
            $table->string('status')->default('received'); // received, created, sent, failed
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_invoices');
    }
};
