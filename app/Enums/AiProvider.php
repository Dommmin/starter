<?php

namespace App\Enums;

/**
 * AI providers an administrator may choose on the System page; values are
 * the provider keys of `config/ai.php`. Other providers stay configurable
 * through the environment only.
 */
enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
}
