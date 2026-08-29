<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable, effective-dated. employee_id null means a
     * tenant-wide default rule — never hard-coded to one person, even
     * though today only one employee actually has a rule.
     */
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('basis', 20)->default('gross_profit');
            $table->decimal('rate', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('employee_id');
            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
