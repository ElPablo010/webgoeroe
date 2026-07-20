<?php

namespace App\Console\Commands;

use App\Models\CaseStudy;
use App\Models\Post;
use App\Support\MediaPath;
use Illuminate\Console\Command;

/**
 * Kuist bestaande records op die een absolute media-URL bevatten (bv. met een
 * localhost-host, ontstaan doordat APP_URL op de server verkeerd stond). De
 * modellen normaliseren voortaan bij het schrijven; dit is de eenmalige inhaalslag.
 */
class NormalizeMediaUrls extends Command
{
    protected $signature = 'media:normalize-urls {--dry-run : Toon enkel wat er zou veranderen}';

    protected $description = 'Zet absolute media-URL\'s in posts en cases om naar relatieve /storage/-paden';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (Post::all() as $post) {
            $before = [$post->cover_url, $post->author_avatar_url, $post->seo_image_url];

            // Toewijzen triggert de mutators op het model.
            $post->cover_url = $post->cover_url;
            $post->author_avatar_url = $post->author_avatar_url;
            $post->seo_image_url = $post->seo_image_url;

            if ($before !== [$post->cover_url, $post->author_avatar_url, $post->seo_image_url]) {
                $this->line("post #{$post->id} ({$post->slug})");
                $changed++;
                $dryRun || $post->save();
            }
        }

        foreach (CaseStudy::all() as $case) {
            $beforeCover = $case->cover_url;
            $beforeSeo = $case->seo_image_url;
            $beforeContent = $case->content;

            $case->cover_url = $case->cover_url;
            $case->seo_image_url = $case->seo_image_url;
            $case->content = $case->content;

            $contentChanged = $beforeContent !== null
                && $beforeContent !== MediaPath::relativeInContent($beforeContent);

            if ($beforeCover !== $case->cover_url || $beforeSeo !== $case->seo_image_url || $contentChanged) {
                $this->line("case #{$case->id} ({$case->slug})");
                $changed++;
                $dryRun || $case->save();
            }
        }

        $this->info($dryRun
            ? "{$changed} record(s) zouden aangepast worden."
            : "{$changed} record(s) genormaliseerd.");

        return self::SUCCESS;
    }
}
