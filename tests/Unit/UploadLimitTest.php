<?php

namespace Tests\Unit;

use App\Support\UploadLimit;
use Tests\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_a_numeric_limit_becomes_a_max_rule(): void
    {
        config(['templates.max_video_kb' => 100000]);

        $this->assertSame(['max:100000'], UploadLimit::rule('templates.max_video_kb'));
    }

    /**
     * The whole point of the class: `'max:'.null` is `'max:'`, which Laravel
     * rejects as a malformed rule at validation time rather than ignoring.
     */
    public function test_no_limit_produces_no_rule_at_all(): void
    {
        config(['templates.max_video_kb' => null]);

        $this->assertSame([], UploadLimit::rule('templates.max_video_kb'));
    }

    public function test_a_string_limit_from_env_is_coerced(): void
    {
        config(['templates.max_video_kb' => '2048']);

        $this->assertSame(['max:2048'], UploadLimit::rule('templates.max_video_kb'));
    }

    public function test_describe_reads_as_a_size_when_limited(): void
    {
        config(['templates.max_music_kb' => 51200]);

        $this->assertSame('up to 51MB', UploadLimit::describe('templates.max_music_kb'));
    }

    public function test_describe_says_so_when_unlimited(): void
    {
        config(['templates.max_music_kb' => null]);

        $this->assertSame('no size limit', UploadLimit::describe('templates.max_music_kb'));
    }
}
