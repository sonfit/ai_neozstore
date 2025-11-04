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
        Schema::create('task_list_tong_hop_tinh_hinh', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->foreignId('tong_hop_tinh_hinh_id')->constrained('tong_hop_tinh_hinhs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_list_id', 'tong_hop_tinh_hinh_id'], 'tl_thh_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_list_tong_hop_tinh_hinh');
    }
};
