<?php

namespace App\Enums;

/**
 * Closed set of "rewrite text" modes of the admin assistant. Users pick a
 * mode, never a free-form instruction, so the input text stays plain data.
 */
enum TextRewriteMode: string
{
    case Shorten = 'shorten';
    case Simplify = 'simplify';
    case Fix = 'fix';
    case ToneFormal = 'tone_formal';
    case ToneCasual = 'tone_casual';

    /**
     * Instruction of the mode for the rewrite agent.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Shorten => 'Shorten the text: keep its key facts and meaning, drop repetition and filler.',
            self::Simplify => 'Simplify the text: use short sentences and everyday words a broad audience understands.',
            self::Fix => 'Fix spelling, grammar and punctuation only; keep the wording, tone and meaning otherwise unchanged.',
            self::ToneFormal => 'Rewrite the text in a formal, professional tone without changing its meaning.',
            self::ToneCasual => 'Rewrite the text in a friendly, casual tone without changing its meaning.',
        };
    }
}
