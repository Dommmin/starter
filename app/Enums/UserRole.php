<?php

namespace App\Enums;

/**
 * Static roles that grant access to the administration panel.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
}
