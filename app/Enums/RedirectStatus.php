<?php

namespace App\Enums;

/**
 * HTTP status of a managed redirect: permanent for moved pages (search
 * engines transfer the ranking), temporary for short campaigns.
 */
enum RedirectStatus: int
{
    case Permanent = 301;
    case Temporary = 302;
}
