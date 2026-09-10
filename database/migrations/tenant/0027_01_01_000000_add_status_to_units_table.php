<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A unit can never be hard-deleted once it's live — it's
     * referenced by unit_conversions (restrictOnDelete), products,
     * sale_items, and purchase_items, so the database itself would
     * refuse the delete the moment it's actually in use. This gives
     * it the same status-toggle "delete" every other module already
     * has instead of no delete path at all.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('status')->default('active')->after('abbreviation');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
