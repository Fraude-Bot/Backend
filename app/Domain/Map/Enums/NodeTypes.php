<?php

namespace App\Domain\Map\Enums;

enum NodeTypes: string
{
    case PARTY = 'party';
    case CONTACT = 'contact';
    case PAYMENT_METHOD = 'payment_method';
}
