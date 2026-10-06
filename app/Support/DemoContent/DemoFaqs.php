<?php

namespace App\Support\DemoContent;

use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\FaqSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample FAQs of {@see FaqSeeder}, matched by language and question; edited
 * when changed after creation. Deleted like in the admin panel (FAQ
 * deletion has no audit entry).
 */
class DemoFaqs implements DemoContentProvider
{
    public function label(): string
    {
        return 'faqs';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        $records = [];

        foreach (FaqSeeder::FAQS as $definition) {
            $faq = Faq::query()
                ->where('locale', $definition['locale'])
                ->where('question', $definition['question'])
                ->first();

            if ($faq !== null) {
                $records[] = $faq;
            }
        }

        return $records;
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $faq = $this->faq($record);

        return $faq->updated_at !== null
            && $faq->created_at !== null
            && $faq->updated_at->gt($faq->created_at);
    }

    public function describe(Model $record): string
    {
        return $this->faq($record)->question;
    }

    public function delete(Model $record, User $actor): void
    {
        $this->faq($record)->delete();
    }

    private function faq(Model $record): Faq
    {
        if (! $record instanceof Faq) {
            throw new InvalidArgumentException('Expected a FAQ.');
        }

        return $record;
    }
}
