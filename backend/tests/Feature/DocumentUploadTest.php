<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Restaurant::factory()->create();
    }

    public function test_an_admin_uploads_a_markdown_document_which_is_chunked(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $markdown = "# Delivery FAQ\n\n## Fees\n\nDelivery costs 3 TND and is free above 60 TND.\n\n## Zones\n\nWe deliver to La Marsa, Carthage and Sidi Bou Said.";
        $file = UploadedFile::fake()->createWithContent('delivery-faq.md', $markdown);

        $response = $this->postJson('/api/documents', ['file' => $file]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Delivery FAQ')
            ->assertJsonPath('data.chunks_count', 2)
            ->assertJsonPath('data.indexed', false); // Typesense disabled in tests

        $document = Document::first();
        $this->assertSame(['Fees', 'Zones'], $document->chunks->pluck('heading')->all());
    }

    public function test_a_custom_title_overrides_the_detected_one(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'Our terrace is open from April to October.'),
            'title' => 'Terrace season',
        ])->assertCreated()->assertJsonPath('data.title', 'Terrace season');
    }

    public function test_unsupported_files_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/documents', ['file' => UploadedFile::fake()->create('menu.exe', 10, 'application/x-msdownload')])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.file.0', 'Only .txt, .md and .pdf documents are supported.');
    }

    public function test_empty_documents_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/documents', ['file' => UploadedFile::fake()->createWithContent('empty.txt', "  \n\n ")])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'empty_document');
    }

    public function test_customers_cannot_upload_documents(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/documents', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello')])
            ->assertForbidden();
    }

    public function test_an_admin_lists_and_deletes_documents(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $document = Document::factory()->for(Restaurant::first())->create();

        $this->getJson('/api/documents')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/documents/{$document->id}")->assertNoContent();
        $this->assertModelMissing($document);
    }
}
