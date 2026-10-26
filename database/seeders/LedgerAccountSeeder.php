<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => 'cash_in_hand', 'name' => 'Cash in Hand', 'group' => 'cash_bank', 'nature' => 'debit', 'is_cash' => true],
            ['code' => 'accounts_receivable', 'name' => 'Accounts Receivable', 'group' => 'party', 'nature' => 'debit'],
            ['code' => 'accounts_payable', 'name' => 'Accounts Payable', 'group' => 'party', 'nature' => 'credit'],
            ['code' => 'inventory', 'name' => 'Inventory', 'group' => 'inventory', 'nature' => 'debit'],
            // Value of raw material issued to production lots that haven't
            // reached Finishing yet — see ProductionOrderController.
            ['code' => 'work_in_progress', 'name' => 'Work in Progress', 'group' => 'inventory', 'nature' => 'debit'],
            ['code' => 'purchase_expense', 'name' => 'Purchase Expense', 'group' => 'income_expense', 'nature' => 'debit'],
            ['code' => 'sales_revenue', 'name' => 'Sales Revenue', 'group' => 'income_expense', 'nature' => 'credit'],
            ['code' => 'cost_of_goods_sold', 'name' => 'Cost of Goods Sold', 'group' => 'income_expense', 'nature' => 'debit'],
            ['code' => 'discount_allowed', 'name' => 'Discount Allowed', 'group' => 'income_expense', 'nature' => 'debit'],

            // Manual expense categories — see ExpenseController. Distinct
            // from purchase_expense/cost_of_goods_sold/discount_allowed
            // above, which only ever get posted automatically and are
            // excluded from the Expense form's category picker.
            ['code' => 'rent_expense', 'name' => 'Rent Expense', 'group' => 'income_expense', 'nature' => 'debit'],
            ['code' => 'utility_expense', 'name' => 'Utility Expense', 'group' => 'income_expense', 'nature' => 'debit'],
            ['code' => 'salary_expense', 'name' => 'Salary Expense', 'group' => 'income_expense', 'nature' => 'debit'],
            ['code' => 'office_expense', 'name' => 'Office Expense', 'group' => 'income_expense', 'nature' => 'debit'],
            ['code' => 'other_expense', 'name' => 'Other Expense', 'group' => 'income_expense', 'nature' => 'debit'],

            // Cost of inventory consumed internally (office/self-use, not a
            // sale) — see InternalConsumptionController. Kept distinct from
            // cost_of_goods_sold (sale-specific) and office_expense (not
            // tied to inventory) so P&L reports this cost on its own line.
            ['code' => 'internal_consumption_expense', 'name' => 'Internal Consumption Expense', 'group' => 'income_expense', 'nature' => 'debit'],

            // WIP written off when a production lot is closed with pieces
            // still unfinished (cutting waste, rejects) — see
            // ProductionOrderController::complete().
            ['code' => 'production_wastage_expense', 'name' => 'Production Wastage', 'group' => 'income_expense', 'nature' => 'debit'],

            // Courier fee, deducted from a COD remittance — see
            // CourierConsignmentController::settleCod().
            ['code' => 'courier_expense', 'name' => 'Courier & Delivery Expense', 'group' => 'income_expense', 'nature' => 'debit'],

            // Manual income categories — see IncomeController. Distinct
            // from sales_revenue above, which only ever gets posted
            // automatically and is excluded from the Income form's
            // category picker.
            ['code' => 'interest_income', 'name' => 'Interest Income', 'group' => 'income_expense', 'nature' => 'credit'],
            ['code' => 'rental_income', 'name' => 'Rental Income', 'group' => 'income_expense', 'nature' => 'credit'],
            ['code' => 'commission_income', 'name' => 'Commission Income', 'group' => 'income_expense', 'nature' => 'credit'],
            ['code' => 'other_income', 'name' => 'Other Income', 'group' => 'income_expense', 'nature' => 'credit'],

            ['code' => 'opening_balance_equity', 'name' => 'Opening Balance Equity', 'group' => 'equity_adjustment', 'nature' => 'credit'],

            // Ongoing owner capital movements — see CapitalTransactionController.
            // Distinct from opening_balance_equity above, which is only for
            // the one-time initial balancing entry.
            ['code' => 'owner_capital', 'name' => "Owner's Capital", 'group' => 'equity_adjustment', 'nature' => 'credit'],
            ['code' => 'owner_drawings', 'name' => "Owner's Drawings", 'group' => 'equity_adjustment', 'nature' => 'debit'],
        ];

        foreach ($accounts as $account) {
            LedgerAccount::firstOrCreate(
                ['code' => $account['code']],
                $account + ['is_system' => true]
            );
        }
    }
}
