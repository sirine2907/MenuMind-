<?php

namespace App\Services\Documents;

/**
 * Splits a document into retrieval-sized chunks.
 *
 * Strategy: walk the text paragraph by paragraph, remembering the current
 * Markdown heading, and pack paragraphs into chunks of at most $maxChars.
 * A heading always starts a new chunk so each chunk stays on one topic, and
 * the heading is kept as metadata so citations can say "Delivery > Fees".
 * Oversized paragraphs are split on sentence boundaries, and consecutive
 * chunks of the same section share a small sentence overlap for context.
 */
class TextChunker
{
    public function __construct(
        private readonly int $maxChars = 700,
        private readonly int $overlapChars = 120,
    ) {}

    /**
     * @return list<array{heading: string|null, content: string}>
     */
    public function chunk(string $text): array
    {
        $chunks = [];
        $heading = null;
        $buffer = '';

        $flush = function () use (&$chunks, &$buffer, &$heading) {
            $content = trim($buffer);
            if ($content !== '') {
                $chunks[] = ['heading' => $heading, 'content' => $content];
            }
            $buffer = '';
        };

        foreach (preg_split('/\n\s*\n/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            // A heading line may be glued to its first paragraph.
            if (preg_match('/^(#{1,6})\s+(.+?)(?:\n(.*))?$/su', $paragraph, $m)) {
                $flush();
                $heading = trim($m[2]);
                $paragraph = trim($m[3] ?? '');
                if ($paragraph === '') {
                    continue;
                }
            }

            foreach ($this->splitLong($paragraph) as $piece) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($piece) + 2 > $this->maxChars) {
                    $overlap = $this->tail($buffer);
                    $flush();
                    $buffer = $overlap;
                }
                $buffer .= ($buffer === '' ? '' : "\n\n").$piece;
            }
        }

        $flush();

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function splitLong(string $paragraph): array
    {
        if (mb_strlen($paragraph) <= $this->maxChars) {
            return [$paragraph];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $paragraph) ?: [$paragraph];
        $pieces = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $this->maxChars) {
                $pieces[] = $current;
                $current = '';
            }
            // A single "sentence" longer than the limit is hard-wrapped.
            while (mb_strlen($sentence) > $this->maxChars) {
                $pieces[] = mb_substr($sentence, 0, $this->maxChars);
                $sentence = mb_substr($sentence, $this->maxChars);
            }
            $current .= ($current === '' ? '' : ' ').$sentence;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * Last full sentence(s) of a chunk, up to the overlap budget.
     */
    private function tail(string $buffer): string
    {
        if ($this->overlapChars <= 0) {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($buffer)) ?: [];
        $tail = '';

        foreach (array_reverse($sentences) as $sentence) {
            $candidate = trim($sentence.' '.$tail);
            if (mb_strlen($candidate) > $this->overlapChars) {
                break;
            }
            $tail = $candidate;
        }

        return $tail;
    }
}
