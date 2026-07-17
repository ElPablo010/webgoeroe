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

#[Description('Zet een bestaande case live op de website.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class PublishCase extends Tool
{
    use InteractsWithCases;

    protected string $name = 'publish_case';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $case = CaseStudy::find($validated['id']);

        if (! $case) {
            return Response::error("Geen case gevonden met id {$validated['id']}.");
        }

        $case->published = true;
        $case->save();

        return Response::json([
            'message' => "Case “{$case->title}” staat nu live.",
            'case' => $this->summarize($case),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Het id van de te publiceren case (via list_cases).')
                ->required(),
        ];
    }
}
