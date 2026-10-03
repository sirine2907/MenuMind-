<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;

// Small wrapper around the Typesense REST API, using Laravel's HTTP client
// (so it is easy to fake in the tests).
class TypesenseClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private int $timeout = 10,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('typesense');
        $url = $config['protocol'].'://'.$config['host'].':'.$config['port'];

        return new self($url, (string) $config['api_key'], (int) $config['timeout']);
    }

    public function isHealthy(): bool
    {
        try {
            return $this->request(2)->get('/health')->json('ok') === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function collectionExists(string $name): bool
    {
        return $this->request()->get("/collections/{$name}")->successful();
    }

    public function createCollection(array $schema): array
    {
        // the first time, Typesense downloads the embedding model, so this can be slow
        return $this->request(300)->post('/collections', $schema)->throw()->json();
    }

    public function deleteCollection(string $name): void
    {
        $response = $this->request()->delete("/collections/{$name}");

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    // Imports many documents at once (one JSON object per line)
    public function import(string $collection, array $documents): int
    {
        if (count($documents) === 0) {
            return 0;
        }

        $lines = [];
        foreach ($documents as $document) {
            $lines[] = json_encode($document, JSON_UNESCAPED_UNICODE);
        }

        $response = $this->request(300)
            ->withBody(implode("\n", $lines), 'text/plain')
            ->post("/collections/{$collection}/documents/import?action=upsert")
            ->throw();

        $imported = 0;
        foreach (explode("\n", trim($response->body())) as $line) {
            $result = json_decode($line, true);

            if (($result['success'] ?? false) !== true) {
                throw new \RuntimeException('Typesense import failed: '.($result['error'] ?? $line));
            }

            $imported++;
        }

        return $imported;
    }

    public function upsert(string $collection, array $document): void
    {
        $this->request(60)->post("/collections/{$collection}/documents?action=upsert", $document)->throw();
    }

    public function delete(string $collection, string $id): void
    {
        $response = $this->request()->delete("/collections/{$collection}/documents/{$id}");

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    public function deleteByFilter(string $collection, string $filter): void
    {
        $response = $this->request()->delete("/collections/{$collection}/documents?".http_build_query(['filter_by' => $filter]));

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    public function search(string $collection, array $params): array
    {
        return $this->request()->get("/collections/{$collection}/documents/search", $params)->throw()->json();
    }

    private function request(?int $timeout = null)
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-TYPESENSE-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->timeout($timeout ?? $this->timeout);
    }
}
