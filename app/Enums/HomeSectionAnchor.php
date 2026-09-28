<?php

namespace App\Enums;

/**
 * Fixed `id` of each rendered home page section. Navigation items link to
 * `#<anchor>`; the values never depend on content or locale.
 */
enum HomeSectionAnchor: string
{
    case Hero = 'hero';
    case Features = 'features';
    case Faq = 'faq';
    case Testimonials = 'testimonials';
    case LatestArticles = 'latest-articles';
    case Contact = 'contact';
    case Cta = 'cta';
}
