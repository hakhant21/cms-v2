<?php

namespace Tests;

use App\Models\Language;
use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings and languages are read through a static cache so a page view
        // costs one query instead of forty. A cache that outlives its test would
        // hand the next test the previous one's rows — database resets do not
        // reach static properties — so both are dropped before every test.
        Setting::forgetCache();
        Language::forgetCache();
    }
}
