<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithPosts;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Haal een blogartikel offline (terug naar concept). Handig om een per ongeluk gepubliceerd artikel meteen weg te halen. De inhoud blijft bewaard.')]
// Verwijdert niets (inhoud blijft), enkel de zichtbaarheid gaat uit.
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class UnpublishPost extends Tool
{
    use InteractsWithPosts;

    protected string $name = 'unpublish_post';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $post = Post::find($validated['id']);

        if (! $post) {
            return Response::error("Geen blogartikel gevonden met id {$validated['id']}.");
        }

        $post->published = false;
        $post->save();

        return Response::json([
            'message' => "Blogartikel “{$post->title}” is offline gehaald en staat weer op concept.",
            'post' => $this->summarize($post),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Het id van het offline te halen artikel (via list_posts).')
                ->required(),
        ];
    }
}
