<?php

namespace Qstom\Minimax;

/**
 * PaymentStatus of an issued invoice, as Minimax reports it.
 */
enum PaymentStatus: string
{
    case Paid = 'Placan';
    case PartiallyPaidOverdue = 'DelnoPlacanZapadel';
    case PartiallyPaidNotDue = 'DelnoPlacanNezapadel';
    case UnpaidOverdue = 'NeplacanZapadel';
    case UnpaidNotDue = 'NeplacanNezapadel';
    case Draft = 'Osnutek';
    case AdvancePayment = 'Avans';

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isOverdue(): bool
    {
        return in_array($this, [self::PartiallyPaidOverdue, self::UnpaidOverdue], true);
    }
}
