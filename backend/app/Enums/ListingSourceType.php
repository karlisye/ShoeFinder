<?php

namespace App\Enums;

enum ListingSourceType: string
{
    case Manual = 'manual';
    case Feed = 'feed';
    case Api = 'api';
}
