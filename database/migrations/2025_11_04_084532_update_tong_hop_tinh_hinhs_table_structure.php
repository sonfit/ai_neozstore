<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tong_hop_tinh_hinhs', function (Blueprint $table) {
            // Thêm cột id_bot
            $table->unsignedBigInteger('id_bot')
                ->nullable()
                ->after('id')
                ->comment('ID Bot, có thể để trống nếu chưa xác định');
            
            // Thêm cột diem
            $table->integer('diem')
                ->default(0)
                ->after('id_muctieu')
                ->comment('Tổng điểm từ keywords');
            
            // Đổi cột pic từ string sang json
            $table->json('pic')->nullable()->change();
            
            // Thêm foreign key cho id_bot
            $table->foreign('id_bot')
                ->references('id')
                ->on('bots')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tong_hop_tinh_hinhs', function (Blueprint $table) {
            $table->dropForeign(['id_bot']);
            $table->dropColumn(['id_bot', 'diem']);
            $table->string('pic', 150)->nullable()->change();
        });
    }
};
