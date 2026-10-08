<?php

namespace App\Services\Documents;

use App\Exceptions\ApiException;
use Smalot\PdfParser\Parser as PdfParser;

class TextExtractor
{
    public function __construct(private PdfParser $pdf) {}

    public function extract(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => $this->fromPdf($path),
            'txt', 'md', 'markdown' => (string) file_get_contents($path),
            default => throw new ApiException("Unsupported file type: .{$extension}", 'unsupported_file_type', 415),
        };

        $text = $this->normalize($text);

        if (trim($text) === '') {
            throw new ApiException('The document does not contain any extractable text.', 'empty_document', 422);
        }

        return $text;
    }

    private function fromPdf(string $path): string
    {
        try {
            return $this->pdf->parseFile($path)->getText();
        } catch (\Throwable $e) {
            throw new ApiException('Could not read this PDF: '.$e->getMessage(), 'unreadable_pdf', 422);
        }
    }

    private function normalize(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $text = str_replace(["\r\n", "\r", "\u{FEFF}"], ["\n", "\n", ''], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
