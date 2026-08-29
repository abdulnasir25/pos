<?php

namespace App\Modules\Partners\Enums;

enum PartnerLedgerEntryType: string
{
    case CapitalContribution = 'capital_contribution';
    case CapitalWithdrawal = 'capital_withdrawal';
    case LoanIssued = 'loan_issued';
    case LoanRepayment = 'loan_repayment';
    case ProfitDistribution = 'profit_distribution';
}
