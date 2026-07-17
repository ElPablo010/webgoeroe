<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithCases;
use App\Models\CaseStudy;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Haal een case offline (terug naar concept). Het vangnet als er per ongeluk iets live ging. De inhoud blijft bewaard.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class UnpublishCase extends Tool
{
    use InteractsWithCases;

    protected string $name = 'unpublish_case';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $case = CaseStudy::find($validated['id']);

        if (! $case) {
            return Response::error("Geen case gevonden met id {$validated['id']}.");
        }

        $case->published = false;
        $case->save();

        return Response::json([
            'message' => "Case “{$case->title}” is offline gehaald en staat weer op concept.",
            'case' => $this->summarize($case),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Het id van de offline te halen case (via list_cases).')
                ->required(),
        ];
    }
}
