<?php

namespace Tests\Unit;

use App\Support\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Editors paste whatever address their browser shows them. These are the
 * shapes that have to reach a working player, and the ones that must not.
 */
class VideoEmbedTest extends TestCase
{
    public static function youtubeLinks(): array
    {
        return [
            'watch page' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch page with a timestamp' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ'],
            'short share link' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'share link with a timestamp' => ['https://youtu.be/dQw4w9WgXcQ?t=42', 'dQw4w9WgXcQ'],
            'already an embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'short video' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'live stream' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'phone address' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'bare id' => ['dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('youtubeLinks')]
    public function test_every_youtube_address_shape_reaches_the_player(string $link, string $id): void
    {
        $this->assertSame("https://www.youtube-nocookie.com/embed/{$id}", VideoEmbed::url($link));
        $this->assertSame("https://i.ytimg.com/vi/{$id}/hqdefault.jpg", VideoEmbed::thumbnail($link));
        $this->assertSame('youtube', VideoEmbed::provider($link));
    }

    public function test_a_vimeo_page_and_its_player_address_both_work(): void
    {
        $this->assertSame(
            'https://player.vimeo.com/video/76979871',
            VideoEmbed::url('https://vimeo.com/76979871'),
        );

        $this->assertSame(
            'https://player.vimeo.com/video/76979871',
            VideoEmbed::url('https://player.vimeo.com/video/76979871'),
        );

        $this->assertNull(VideoEmbed::thumbnail('https://vimeo.com/76979871'));
    }

    public function test_something_that_is_not_a_video_is_refused(): void
    {
        foreach (['', '   ', 'https://example.com/page', 'https://www.youtube.com/@ruma', 'not a link'] as $link) {
            $this->assertNull(VideoEmbed::url($link), "[{$link}] must not be treated as a video.");
            $this->assertFalse(VideoEmbed::isEmbeddable($link));
        }
    }
}
