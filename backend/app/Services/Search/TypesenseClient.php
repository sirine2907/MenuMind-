<?php

namespace App\Services\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Typesense REST API built on Laravel's HTTP client,
 * which keeps it dependency-free and trivially fakeable in tests.
 */
class TypesenseClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 10,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('typesense');

        return new self(
            sprintf('%s://%s:%s', $config['protocol'], $config['host'], $config['port']),
            (string) $config['api_key'],
            (int) $config['timeout'],
        );
    }

    public function isHealthy(): bool
    {
        try {
            return $this->request(2)->get('/health')->json('ok') === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function collectionExists(string $name): bool
    {
        return $this->request()->get("/collections/{$name}")->successful();
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function createCollection(array $schema): array
    {
        // Creating a collection with an auto-embedding field downloads the model
        // the first time, which can take a while.
        return $this->request(300)->post('/collections', $schema)->throw()->json();
    }

    public function deleteCollection(string $name): void
    {
        $response = $this->request()->delete("/collections/{$name}");

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * Bulk upsert through the JSONL import endpoint.
     *
     * @param  list<array<string, mixed>>  $documents
     * @return int number of successfully imported documents
     */
    public function import(string $collection, array $documents): int
    {
        if ($documents === []) {
            return 0;
        }

        $body = implode("\n", array_map(fn ($doc) => json_encode($doc, JSON_UNESCAPED_UNICODE), $documents));

        $response = $this->request(300)
            ->withBody($body, 'text/plain')
            ->post("/collections/{$collection}/documents/import?action=upsert")
            ->throw();

        $results = array_filter(explode("\n", trim($response->body())));
        $ok = 0;

        foreach ($results as $line) {
            $result = json_decode($line, true);
            if (($result['success'] ?? false) === true) {
                $ok++;
            } else {
                throw new \RuntimeException('Typesense import failed: '.($result['error'] ?? $line));
            }
        }

        return $ok;
    }

    /**
     * @param  array<string, mixed>  $document
     */
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

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function search(string $collection, array $params): array
    {
        return $this->request()->get("/collections/{$collection}/documents/search", $params)->throw()->json();
    }

    private function request(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-TYPESENSE-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->timeout($timeout ?? $this->timeout);
    }
}
