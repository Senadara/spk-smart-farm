<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable()->comment('ID user dari API backend');
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('login_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
