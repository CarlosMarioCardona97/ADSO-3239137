<?php

namespace App\Domain;

/** Roles de los operadores internos. El funcionario no es usuario: no tiene rol. */
enum Role: string
{
    case Admin = 'admin';
    case Archivist = 'archivist';
}
