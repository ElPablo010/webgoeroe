<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(): Response
    {
        $previewDrafts = auth()->check();

        $posts = Post::query()
            ->when(! $previewDrafts, fn ($q) => $q->where('published', true))
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->view('blog.index', [
            'posts' => $posts,
            'seo'   => Seo::fromBlogIndex(),
        ]);
    }

    public function show(string $slug): Response
    {
        $previewDrafts = auth()->check();

        $post = Post::query()
            ->where('slug', $slug)
            ->when(! $previewDrafts, fn ($q) => $q->where('published', true))
            ->firstOrFail();

        $related = Post::query()
            ->where('id', '!=', $post->id)
            ->where('published', true)
            ->when(! empty($post->tags), function ($q) use ($post) {
                // Haal posts op die minstens één tag gemeen hebben
                $q->where(function ($inner) use ($post) {
                    foreach ($post->tags as $tag) {
                        $inner->orWhereJsonContains('tags', $tag);
                    }
                });
            })
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return response()->view('blog.show', [
            'post'    => $post,
            'related' => $related,
            'seo'     => Seo::fromPost($post),
        ]);
    }
}
