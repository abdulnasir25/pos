<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal, scoped-down from the full architecture doc: no branch_id
     * yet, since the Branches module isn't built in this phase. Add it
     * as a nullable FK in a later migration once Branches exists — never
     * retrofit business meaning onto a column that predates it.
     */
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
