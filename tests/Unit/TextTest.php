<?php

namespace Tests\Unit;

use App\Support\Text;
use PHPUnit\Framework\TestCase;

/**
 * The boundary repairs that keep legacy and uploaded strings JSON-safe.
 */
class TextTest extends TestCase
{
    public function test_a_valid_string_is_returned_untouched(): void
    {
        $this->assertSame('Ruma – organic', Text::utf8('Ruma – organic'));
        $this->assertNull(Text::utf8(null));
        $this->assertSame('', Text::utf8(''));
    }

    public function test_windows_1252_bytes_are_converted_instead_of_dropped(): void
    {
        // 0x96 is an en dash in Windows-1252 and invalid on its own in UTF-8.
        $repaired = Text::utf8("Top-\x96-quality.jpg");

        $this->assertTrue(mb_check_encoding($repaired, 'UTF-8'));
        $this->assertSame('Top-–-quality.jpg', $repaired);
    }

    public function test_bytes_that_cannot_be_salvaged_are_removed(): void
    {
        $repaired = Text::utf8("broken-\xE2\x5F\x5F-name.jpg");

        $this->assertTrue(mb_check_encoding($repaired, 'UTF-8'));
        $this->assertStringContainsString('broken-', $repaired);
    }

    public function test_a_file_name_from_a_url_keeps_its_multi_byte_characters(): void
    {
        // Regression: basename() is locale aware and replaced the three bytes
        // of the en dash with underscores, producing invalid UTF-8.
        $this->assertSame(
            'Top-–-quality-composts.jpg',
            Text::fileName('https://example.com/uploads/2025/12/Top-–-quality-composts.jpg'),
        );
    }

    public function test_a_file_name_is_url_decoded(): void
    {
        $this->assertSame('my photo.jpg', Text::fileName('https://example.com/uploads/my%20photo.jpg'));
    }
}
