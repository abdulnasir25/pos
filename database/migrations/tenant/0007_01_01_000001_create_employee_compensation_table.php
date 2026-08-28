<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Effective-dated salary history. A raise is a new row — the
     * monthly_salary of an existing row is never rewritten. The one
     * mutation this table's rows ever receive is effective_to being set
     * on the previously-open record at the moment a new one supersedes
     * it (see RecordSalaryChange); that's a boundary adjustment, not a
     * rewrite of what salary was actually paid during that row's span.
     */
    public function up(): void
    {
        Schema::create('employee_compensation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_salary', 14, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensation');
    }
};
