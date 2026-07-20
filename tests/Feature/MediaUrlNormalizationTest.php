<?php

use App\Models\CaseStudy;
use App\Models\Post;
use App\Support\MediaPath;

it('stores an absolute library url as a relative path', function () {
    $case = CaseStudy::create([
        'title' => 'Test',
        'slug' => 'test-case',
        'cover_url' => 'http://localhost:8000/storage/website-media/abc.webp',
        'content' => ['challenge' => ['body' => 'x']],
    ]);

    expect($case->fresh()->cover_url)->toBe('/storage/website-media/abc.webp');
});

it('normalizes media urls nested in case content', function () {
    $case = CaseStudy::create([
        'title' => 'Test',
        'slug' => 'test-case-2',
        'content' => [
            'challenge' => ['body' => 'x'],
            'solution' => [
                'body' => 'y',
                'image_url' => 'https://example.test/storage/website-media/def.webp',
            ],
            'testimonial' => ['avatar_url' => 'http://localhost:8000/storage/website-media/ghi.webp'],
        ],
    ]);

    $content = $case->fresh()->content;

    expect($content['solution']['image_url'])->toBe('/storage/website-media/def.webp')
        ->and($content['testimonial']['avatar_url'])->toBe('/storage/website-media/ghi.webp')
        ->and($content['solution']['body'])->toBe('y');
});

it('leaves relative paths and non-library urls untouched', function () {
    expect(MediaPath::relative('/storage/website-media/abc.webp'))->toBe('/storage/website-media/abc.webp')
        ->and(MediaPath::relative('https://cdn.example.com/logo.png'))->toBe('https://cdn.example.com/logo.png')
        ->and(MediaPath::relative(null))->toBeNull();
});

it('normalizes post cover urls', function () {
    $post = Post::create([
        'title' => 'Test',
        'slug' => 'test-post',
        'body' => 'x',
        'cover_url' => 'http://localhost:8000/storage/website-media/jkl.webp',
    ]);

    expect($post->fresh()->cover_url)->toBe('/storage/website-media/jkl.webp');
});
