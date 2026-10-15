<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignedStorageUploadTest extends TestCase
{
    /**
     * D4 / M-R0-13: storage.local.upload tanpa valid signature mengembalikan 403.
     */
    public function test_storage_local_upload_without_valid_signature_returns_403(): void
    {
        $response = $this->put('/storage/unauthorized-file.txt');

        $response->assertForbidden();
    }

    /**
     * D4 / M-R0-13: storage.local.upload dengan valid temporary signed URL tidak mengembalikan 403.
     */
    public function test_storage_local_upload_with_valid_signature_is_not_forbidden(): void
    {
        $uploadData = Storage::disk('local')->temporaryUploadUrl(
            'test-authorized-file.txt',
            now()->addMinutes(10)
        );

        $url = is_array($uploadData) ? $uploadData['url'] : $uploadData;
        $response = $this->call('PUT', $url, [], [], [], [], 'hello raw content');

        $this->assertNotSame(403, $response->status(), 'Signed route storage.local.upload dengan valid signature tidak boleh mengembalikan 403.');
        $this->assertSame(204, $response->status());
    }
}
