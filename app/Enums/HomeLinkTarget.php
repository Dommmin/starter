<?php

namespace App\Enums;

/**
 * Closed set of destinations a home page action can point to. URLs are
 * resolved on the backend for the current locale; an unavailable target
 * (missing route, unpublished page) drops the action.
 */
enum HomeLinkTarget: string
{
    /** The contact section of the same page (`#contact`). */
    case Contact = 'contact';
    case Articles = 'articles';
    case Login = 'login';
    case Register = 'register';
    /** A published CMS page selected by `pageId`. */
    case Page = 'page';
}
