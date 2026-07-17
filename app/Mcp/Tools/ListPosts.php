<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithPosts;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lijst de bestaande blogartikelen op (nieuwste eerst), met id, titel, slug en publicatiestatus. Gebruik dit om het id van een artikel te vinden of om te controleren of een titel al bestaat.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListPosts extends Tool
{
    use InteractsWithPosts;

    protected string $name = 'list_posts';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:all,published,draft'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'all';
        $limit = $validated['limit'] ?? 25;

        $posts = Post::query()
            ->when(
                filled($validated['search'] ?? null),
                fn ($q) => $q->where('title', 'like', '%'.$validated['search'].'%')
            )
            ->when($status === 'published', fn ($q) => $q->where('published', true))
            ->when($status === 'draft', fn ($q) => $q->where('published', false))
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return Response::json([
            'count' => $posts->count(),
            'posts' => $posts->map(fn (Post $post) => $this->summarize($post))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optioneel. Filter op (deel van) de titel.'),
            'status' => $schema->string()
                ->description('Filter op status: all (standaard), published of draft.')
                ->enum(['all', 'published', 'draft']),
            'limit' => $schema->integer()
                ->description('Maximum aantal resultaten (1-100, standaard 25).'),
        ];
    }
}
