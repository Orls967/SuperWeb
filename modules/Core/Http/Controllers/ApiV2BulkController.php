<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Application\Api\AsyncZipExportService;
use Modules\Core\Application\Api\StreamingBulkImportService;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Domain\Models\CoreBulkExportJob;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApiV2BulkController extends Controller
{
    public function __construct(
        private readonly StreamingBulkImportService $importService,
        private readonly AsyncZipExportService $exportService,
    ) {}

    public function import(Request $request): JsonResponse
    {
        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        $targetKey = (string) $request->input('target_key');

        $filePath = '';
        $originalFilename = 'data.csv';

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $filePath = $uploaded->getRealPath();
            $originalFilename = $uploaded->getClientOriginalName();
        } elseif ($request->has('file_path')) {
            $filePath = (string) $request->input('file_path');
            $originalFilename = basename($filePath);
        } else {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/missing-file',
                'title' => 'Missing Import File',
                'status' => 400,
                'detail' => 'File upload or file_path is required.',
            ], 400);
        }

        $job = $this->importService->validateFile(
            tenantId: $context->tenantId,
            targetKey: $targetKey,
            filePath: $filePath,
            originalFilename: $originalFilename,
            requestedByUserId: Auth::id()
        );

        if ($job->status === 'validated') {
            $job = $this->importService->executeJob($job);
        }

        return response()->json([
            'data' => [
                'job_id' => $job->id,
                'target_key' => $job->target_key,
                'status' => $job->status,
                'total_rows' => $job->total_rows,
                'valid_rows' => $job->valid_rows,
                'error_rows' => $job->error_rows,
                'processed_rows' => $job->processed_rows,
            ],
        ], 201);
    }

    public function export(Request $request): JsonResponse
    {
        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        $targetKey = (string) $request->input('target_key');
        $maxRows = (int) $request->input('max_rows', 50000);

        $job = $this->exportService->export(
            tenantId: $context->tenantId,
            targetKey: $targetKey,
            requestedByUserId: Auth::id(),
            maxRows: $maxRows
        );

        $downloadUrl = $this->exportService->generateDownloadUrl($job);

        return response()->json([
            'data' => [
                'job_id' => $job->id,
                'target_key' => $job->target_key,
                'status' => $job->status,
                'total_rows' => $job->total_rows,
                'file_sha256' => $job->file_sha256,
                'download_url' => $downloadUrl,
            ],
        ], 201);
    }

    public function download(Request $request, int $jobId): BinaryFileResponse|JsonResponse
    {
        if (! $request->user() && ! $request->hasValidSignature()) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/invalid-signature',
                'title' => 'Invalid or Expired Signed URL',
                'status' => 403,
            ], 403);
        }

        /** @var CoreBulkExportJob $job */
        $job = CoreBulkExportJob::findOrFail($jobId);

        if (! $job->zip_path || ! file_exists($job->zip_path)) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/file-not-found',
                'title' => 'Export Archive File Not Found',
                'status' => 404,
            ], 404);
        }

        return response()->download($job->zip_path, basename($job->zip_path), [
            'Content-Type' => 'application/zip',
            'X-File-SHA256' => (string) $job->file_sha256,
        ]);
    }

    public function downloadExport(Request $request): BinaryFileResponse|JsonResponse
    {
        $exportId = (int) ($request->query('export') ?? $request->input('export', 0));

        return $this->download($request, $exportId);
    }
}
