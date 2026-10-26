<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seeders write slide images to the public disk and SPT PDFs to the local disk; never touch the real storage from tests.
        Storage::fake('public');
        Storage::fake('local');
    }
}
