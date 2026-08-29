<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The general ledger's account list. parent_id makes it a tree
     * (e.g. per-partner Capital/Drawings sub-accounts can be added
     * later under the fixed parents seeded here). type is one of the
     * five accounting classifications, plus contra_equity for Drawings
     * — it moves opposite to Equity, so folding it into 'equity' would
     * misrepresent which side of the account increases it.
     *
     * Seeded with the fixed chart from FINANCIAL-SCHEMA-DESIGN.md §K on
     * every tenant, standard numbering (1000s Asset, 2000s Liability,
     * 3000s Equity, 4000s Revenue, 5000s Expense).
     */
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('type');
            $table->index('parent_id');
        });

        $this->seedStandardChart();
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }

    private function seedStandardChart(): void
    {
        $now = now();

        $accounts = [
            ['code' => '1000', 'name' => 'Cash and Bank', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Partner Loans Payable', 'type' => 'liability'],
            ['code' => '3000', 'name' => 'Partner Capital', 'type' => 'equity'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity'],
            ['code' => '3200', 'name' => 'Drawings', 'type' => 'contra_equity'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'Salary Expense', 'type' => 'expense'],
            ['code' => '5200', 'name' => 'Commission Expense', 'type' => 'expense'],
            ['code' => '5300', 'name' => 'Operating Expenses', 'type' => 'expense'],
        ];

        foreach ($accounts as &$account) {
            $account['status'] = 'active';
            $account['created_at'] = $now;
            $account['updated_at'] = $now;
        }
        unset($account);

        DB::table('chart_of_accounts')->insert($accounts);
    }
};
