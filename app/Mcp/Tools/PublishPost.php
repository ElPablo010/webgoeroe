<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithPosts;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Zet een bestaand blogartikel live. Vult de publicatiedatum aan met nu indien nog leeg.')]
class PublishPost extends Tool
{
    use InteractsWithPosts;

    protected string $name = 'publish_post';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $post = Post::find($validated['id']);

        if (! $post) {
            return Response::error("Geen blogartikel gevonden met id {$validated['id']}.");
        }

        $post->published = true;
        $post->published_at ??= now();
        $post->save();

        return Response::json([
            'message' => "Blogartikel “{$post->title}” staat nu live.",
            'post' => $this->summarize($post),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Het id van het te publiceren artikel (via list_posts).')
                ->required(),
        ];
    }
}
