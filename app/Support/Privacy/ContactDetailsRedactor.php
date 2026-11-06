<?php

namespace App\Support\Privacy;

/**
 * Replace e-mail addresses and phone numbers in free text with placeholders
 * before the text leaves the application (AI prompts). Errs on the side of
 * redacting: any run of at least 7 digits with common phone separators is
 * treated as a phone number.
 */
class ContactDetailsRedactor
{
    public const string EMAIL_PLACEHOLDER = '[email]';

    public const string PHONE_PLACEHOLDER = '[phone]';

    private const string EMAIL_PATTERN = '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)+/u';

    private const string PHONE_PATTERN = '/\+?\(?\d[\d\s().\/\-]{5,}\d/u';

    private const int PHONE_MIN_DIGITS = 7;

    public function redact(string $text): string
    {
        $text = (string) preg_replace(self::EMAIL_PATTERN, self::EMAIL_PLACEHOLDER, $text);

        return (string) preg_replace_callback(
            self::PHONE_PATTERN,
            fn (array $match): string => preg_match_all('/\d/', $match[0]) >= self::PHONE_MIN_DIGITS
                ? self::PHONE_PLACEHOLDER
                : $match[0],
            $text,
        );
    }
}
