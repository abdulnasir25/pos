<?php

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

class InvoiceAlreadyPaidException extends RuntimeException
{
    public static function forInvoice(int $invoiceId): self
    {
        return new self("Invoice #{$invoiceId} is already marked paid.");
    }
}
