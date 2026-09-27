<?php

namespace App\Services\Documents;

// Splits a document into small pieces ("chunks") for the RAG search.
// Every markdown heading starts a new chunk and is saved with it, so the
// assistant can say where an answer comes from (e.g. "Delivery > Fees").
class TextChunker
{
    public function __construct(private int $maxLength = 700) {}

    public function chunk(string $text): array
    {
        $chunks = [];
        $heading = null;
        $current = '';

        foreach (preg_split('/\n\s*\n/', $text) as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (preg_match('/^#{1,6}\s/', $paragraph)) {
                // close the current chunk, a new section starts here
                $chunks = $this->addChunk($chunks, $heading, $current);
                $current = '';

                // the heading can be followed by text on the next line
                $lines = explode("\n", $paragraph, 2);
                $heading = trim(ltrim($lines[0], '#'));
                $paragraph = trim($lines[1] ?? '');

                if ($paragraph === '') {
                    continue;
                }
            }

            foreach ($this->splitLongParagraph($paragraph) as $part) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($part) + 2 > $this->maxLength) {
                    $chunks = $this->addChunk($chunks, $heading, $current);
                    $current = '';
                }

                $current = $current === '' ? $part : $current."\n\n".$part;
            }
        }

        return $this->addChunk($chunks, $heading, $current);
    }

    private function addChunk(array $chunks, ?string $heading, string $content): array
    {
        if (trim($content) !== '') {
            $chunks[] = ['heading' => $heading, 'content' => trim($content)];
        }

        return $chunks;
    }

    // Paragraphs that are too long are cut between sentences
    private function splitLongParagraph(string $paragraph): array
    {
        if (mb_strlen($paragraph) <= $this->maxLength) {
            return [$paragraph];
        }

        $parts = [];
        $current = '';

        foreach (preg_split('/(?<=[.!?])\s+/', $paragraph) as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $this->maxLength) {
                $parts[] = $current;
                $current = '';
            }

            // a single sentence longer than the limit is simply cut
            while (mb_strlen($sentence) > $this->maxLength) {
                $parts[] = mb_substr($sentence, 0, $this->maxLength);
                $sentence = mb_substr($sentence, $this->maxLength);
            }

            $current = $current === '' ? $sentence : $current.' '.$sentence;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
