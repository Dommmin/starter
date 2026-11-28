<?php

namespace App\Enums;

/**
 * Where the public announcement appears: a bar above the header, or a
 * dismissible popup shown once per visitor and announcement text.
 */
enum AnnouncementDisplay: string
{
    case Bar = 'bar';
    case Popup = 'popup';
}
