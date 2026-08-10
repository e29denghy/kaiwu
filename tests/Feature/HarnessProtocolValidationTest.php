<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class HarnessProtocolValidationTest extends TestCase
{
    public function test_valid_conformance_fixtures_pass(): void
    {
        $this->artisan('harness:validate', [
            'path' => base_path('examples/conformance/valid/events.jsonl'),
        ])->expectsOutput('Valid: 2 document(s).')->assertSuccessful();

        $this->artisan('harness:validate', [
            'path' => base_path('examples/conformance/valid/quest.json'),
        ])->expectsOutput('Valid: 1 document(s).')->assertSuccessful();
    }

    public function test_invalid_conformance_fixtures_fail(): void
    {
        $this->artisan('harness:validate', [
            'path' => base_path('examples/conformance/invalid/events.jsonl'),
        ])->assertFailed();

        $this->artisan('harness:validate', [
            'path' => base_path('examples/conformance/invalid/quest.json'),
        ])->assertFailed();
    }

    public function test_empty_jsonl_file_fails(): void
    {
        $path = sys_get_temp_dir().'/kaiwu-empty-'.Str::uuid().'.jsonl';
        touch($path);

        try {
            $this->artisan('harness:validate', ['path' => $path])
                ->expectsOutput('Invalid: no protocol documents found.')
                ->assertFailed();
        } finally {
            @unlink($path);
        }
    }
}
