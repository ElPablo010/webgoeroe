<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithCases;
use App\Models\CaseStudy;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lijst de bestaande cases (klantprojecten) op, met id, titel, slug, klant en publicatiestatus. Gebruik dit om het id van een case te vinden of om te controleren of een case al bestaat.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListCases extends Tool
{
    use InteractsWithCases;

    protected string $name = 'list_cases';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:all,published,draft'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'all';

        $cases = CaseStudy::query()
            ->when(
                filled($validated['search'] ?? null),
                fn ($q) => $q->where('title', 'like', '%'.$validated['search'].'%')
                    ->orWhere('client', 'like', '%'.$validated['search'].'%')
            )
            ->when($status === 'published', fn ($q) => $q->where('published', true))
            ->when($status === 'draft', fn ($q) => $q->where('published', false))
            ->orderByDesc('featured')
            ->orderByDesc('created_at')
            ->limit($validated['limit'] ?? 25)
            ->get();

        return Response::json([
            'count' => $cases->count(),
            'cases' => $cases->map(fn (CaseStudy $case) => $this->summarize($case))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optioneel. Filter op (deel van) de titel of de klantnaam.'),
            'status' => $schema->string()
                ->description('Filter op status: all (standaard), published of draft.')
                ->enum(['all', 'published', 'draft']),
            'limit' => $schema->integer()
                ->description('Maximum aantal resultaten (1-100, standaard 25).'),
        ];
    }
}
