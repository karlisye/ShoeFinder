<?php

namespace App\Enums;

enum Audience: string
{
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';
    case Kids = 'kids';
}
