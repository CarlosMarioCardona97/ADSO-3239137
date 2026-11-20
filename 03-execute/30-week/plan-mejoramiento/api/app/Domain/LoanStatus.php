<?php

namespace App\Domain;

/** Estado CALCULADO de un préstamo (artículo VII): nunca se guarda en la base. */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
