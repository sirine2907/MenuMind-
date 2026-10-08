<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Search\SearchIndexer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Upload -> extract the text -> split it into chunks -> save the chunks -> index them in Typesense
class DocumentService
{
    public function __construct(
        private TextExtractor $extractor,
        private TextChunker $chunker,
        private SearchIndexer $indexer,
    ) {}

    public function ingestUpload(Restaurant $restaurant, UploadedFile $file, ?string $title, ?User $uploader): Document
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $text = $this->extractor->extract($file->getRealPath(), $extension);

        return $this->ingestText(
            $restaurant,
            $title ?: $this->titleFrom($text, $file->getClientOriginalName()),
            $text,
            $file->getClientOriginalName(),
            $extension === 'pdf' ? 'application/pdf' : ($extension === 'txt' ? 'text/plain' : 'text/markdown'),
            $uploader,
        );
    }

    public function ingestText(
        Restaurant $restaurant,
        string $title,
        string $text,
        string $filename,
        string $mimeType = 'text/markdown',
        ?User $uploader = null,
    ): Document {
        $chunks = $this->chunker->chunk($text);

        $document = DB::transaction(function () use ($restaurant, $title, $text, $filename, $mimeType, $uploader, $chunks) {
            $document = Document::create([
                'restaurant_id' => $restaurant->id,
                'uploaded_by' => $uploader?->id,
                'title' => Str::limit($title, 250, ''),
                'original_filename' => $filename,
                'mime_type' => $mimeType,
                'content' => $text,
                'chunks_count' => count($chunks),
            ]);

            foreach ($chunks as $position => $chunk) {
                $document->chunks()->create([
                    'position' => $position,
                    'heading' => $chunk['heading'] ? Str::limit($chunk['heading'], 250, '') : null,
                    'content' => $chunk['content'],
                ]);
            }

            return $document;
        });

        if ($this->indexer->indexDocument($document->load('chunks'))) {
            $document->forceFill(['indexed_at' => now()])->save();
        }

        return $document;
    }

    public function delete(Document $document): void
    {
        $this->indexer->removeDocument($document->id);
        $document->delete();
    }

    private function titleFrom(string $text, string $filename): string
    {
        if (preg_match('/^#\s+(.+)$/mu', $text, $m)) {
            return trim($m[1]);
        }

        return Str::headline(pathinfo($filename, PATHINFO_FILENAME));
    }
}
