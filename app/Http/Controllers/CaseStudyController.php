<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Support\Seo;
use Illuminate\Http\Response;

class CaseStudyController extends Controller
{
    public function index(): Response
    {
        $previewDrafts = auth()->check();

        $cases = CaseStudy::query()
            ->when(! $previewDrafts, fn ($q) => $q->where('published', true))
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->get();

        return response()->view('case-studies.index', [
            'cases' => $cases,
            'seo' => Seo::fromCaseStudiesIndex(),
        ]);
    }

    public function show(string $slug): Response
    {
        $previewDrafts = auth()->check();

        $case = CaseStudy::query()
            ->where('slug', $slug)
            ->when(! $previewDrafts, fn ($q) => $q->where('published', true))
            ->firstOrFail();

        return response()->view('case-studies.show', [
            'case' => $case,
            'seo' => Seo::fromCaseStudy($case),
        ]);
    }
}
