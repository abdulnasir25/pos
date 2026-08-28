<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The compensation-subject entity — never the same row as a User.
     * user_id is nullable and independently changeable: an employee can
     * exist with no login, gain one, lose it, or move to a different
     * login account, none of which touches this row's identity or its
     * history. No delete route exists at the application layer —
     * termination is a status change (see EmployeeStatus), never a row
     * removal.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->date('hired_at');
            $table->date('terminated_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
