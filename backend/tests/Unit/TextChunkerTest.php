<?php

namespace Tests\Unit;

use App\Services\Documents\TextChunker;
use PHPUnit\Framework\TestCase;

class TextChunkerTest extends TestCase
{
    public function test_each_heading_starts_a_new_chunk_and_is_kept_as_metadata(): void
    {
        $chunks = (new TextChunker)->chunk("# Title\n\nIntro paragraph.\n\n## Fees\n\nThree dinars.\n\n## Zones\nLa Marsa and Carthage.");

        $this->assertSame([
            ['heading' => 'Title', 'content' => 'Intro paragraph.'],
            ['heading' => 'Fees', 'content' => 'Three dinars.'],
            ['heading' => 'Zones', 'content' => 'La Marsa and Carthage.'],
        ], $chunks);
    }

    public function test_paragraphs_are_packed_up_to_the_size_limit(): void
    {
        $text = implode("\n\n", array_fill(0, 6, str_repeat('word ', 18).'end.'));

        $chunks = (new TextChunker(maxChars: 250, overlapChars: 0))->chunk($text);

        $this->assertCount(3, $chunks);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(250, mb_strlen($chunk['content']));
        }
    }

    public function test_long_paragraphs_are_split_on_sentences_with_overlap(): void
    {
        $sentences = array_map(fn ($i) => "Sentence number {$i} talks about couscous.", range(1, 20));

        $chunks = (new TextChunker(maxChars: 200, overlapChars: 60))->chunk(implode(' ', $sentences));

        $this->assertGreaterThan(3, count($chunks));

        foreach ($chunks as $i => $chunk) {
            $this->assertLessThanOrEqual(200, mb_strlen($chunk['content']));

            // The last sentence of a chunk is repeated at the start of the next one.
            if (isset($chunks[$i + 1])) {
                preg_match('/Sentence number \d+ talks about couscous\.$/', $chunk['content'], $m);
                $this->assertStringStartsWith($m[0], $chunks[$i + 1]['content']);
            }
        }
    }

    public function test_it_never_emits_empty_chunks(): void
    {
        $this->assertSame([], (new TextChunker)->chunk("\n\n   \n\n"));
        $this->assertSame([], (new TextChunker)->chunk("# Only a heading\n\n"));
    }
}
