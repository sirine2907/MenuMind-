<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Restaurant;
use App\Services\Documents\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    public function index(): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            Restaurant::current()->documents()->latest('id')->get(),
        );
    }

    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->ingestUpload(
            Restaurant::current(),
            $request->file('file'),
            $request->validated('title'),
            $request->user(),
        );

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function destroy(Document $document): Response
    {
        $this->documents->delete($document);

        return response()->noContent();
    }
}
