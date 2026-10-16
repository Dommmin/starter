<?php

namespace App\Enums;

/**
 * Static roles that grant access to the administration panel. A viewer
 * reads content in the panel without changing anything (owner decision
 * 2026-10-16, wave 5).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
