<?php

namespace App\Enums;

/**
 * Items of the launch checklist on the System page.
 */
enum LaunchCheck: string
{
    /** Every administrator has confirmed two-factor authentication. */
    case AdminTwoFactor = 'admin_two_factor';

    /** A test e-mail was sent from the System page. */
    case TestMail = 'test_mail';

    /** The Google Search Console token is saved in the site settings. */
    case SearchConsole = 'search_console';

    /** Privacy policy, cookie policy and terms are published in the default language. */
    case LegalPages = 'legal_pages';
}
