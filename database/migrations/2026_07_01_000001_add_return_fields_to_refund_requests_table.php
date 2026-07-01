<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('return_request_id')
                ->nullable()
                ->after('order_id')
                ->constrained('return_requests')
                ->nullOnDelete();
            $table->text('note')->nullable()->after('amount');
            $table->string('transfer_image')->nullable()->after('note');
            $table->timestamp('completed_at')->nullable()->after('transfer_image');

            $table->unique('return_request_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropUnique(['return_request_id']);
            $table->dropConstrainedForeignId('return_request_id');
            $table->dropColumn(['note', 'transfer_image', 'completed_at']);
        });
    }
};
