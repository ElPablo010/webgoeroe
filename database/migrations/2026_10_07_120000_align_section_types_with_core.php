<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lijnt de sectietypes en hun content-sleutels uit op de gedeelde core-standaard:
 *
 *   rich_text     → text      (content ongewijzigd)
 *   testimonials  → reviews   (items[]: title→highlight, author→name,
 *                              company→role, avatar→image)
 *   calendly      → booking   (layout=section, calendly_url→url)
 *   booking_hero  → booking   (layout=hero,    calendly_url→url)
 *   hero.size     → hero.height (default/leeg→tall, compact→compact)
 *
 * Werkt op page_sections én op de voorgestelde secties in seo_action_items.proposed
 * (zowel { section_type, content } als { sections: [...] }). Idempotent: al
 * omgezette data blijft ongemoeid. down() draait alles terug.
 */
return new class extends Migration
{
    private const REVIEW_KEYS = [
        'title' => 'highlight',
        'author' => 'name',
        'company' => 'role',
        'avatar' => 'image',
    ];

    public function up(): void
    {
        $this->transform(fn (string $type, array $content): array => $this->toCore($type, $content));
    }

    public function down(): void
    {
        $this->transform(fn (string $type, array $content): array => $this->fromCore($type, $content));
    }

    /**
     * @param  callable(string, array): array{0: string, 1: array}  $map
     */
    private function transform(callable $map): void
    {
        DB::table('page_sections')->lazyById()->each(function ($row) use ($map) {
            $content = json_decode((string) $row->content, true);
            $content = is_array($content) ? $content : [];

            [$type, $newContent] = $map($row->section_type, $content);

            if ($type !== $row->section_type || $newContent !== $content) {
                DB::table('page_sections')->where('id', $row->id)->update([
                    'section_type' => $type,
                    'content' => json_encode($newContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        });

        if (! Schema::hasTable('seo_action_items')) {
            return;
        }

        DB::table('seo_action_items')->whereNotNull('proposed')->lazyById()->each(function ($row) use ($map) {
            $proposed = json_decode((string) $row->proposed, true);

            if (! is_array($proposed)) {
                return;
            }

            $original = $proposed;

            if (isset($proposed['section_type']) && is_string($proposed['section_type'])) {
                [$proposed['section_type'], $proposed['content']] = $map(
                    $proposed['section_type'],
                    is_array($proposed['content'] ?? null) ? $proposed['content'] : [],
                );
            }

            if (isset($proposed['sections']) && is_array($proposed['sections'])) {
                foreach ($proposed['sections'] as $i => $section) {
                    if (! is_array($section) || ! is_string($section['section_type'] ?? null)) {
                        continue;
                    }
                    [$proposed['sections'][$i]['section_type'], $proposed['sections'][$i]['content']] = $map(
                        $section['section_type'],
                        is_array($section['content'] ?? null) ? $section['content'] : [],
                    );
                }
            }

            if ($proposed !== $original) {
                DB::table('seo_action_items')->where('id', $row->id)->update([
                    'proposed' => json_encode($proposed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        });
    }

    /** @return array{0: string, 1: array} */
    private function toCore(string $type, array $content): array
    {
        switch ($type) {
            case 'rich_text':
                return ['text', $content];

            case 'testimonials':
                if (is_array($content['items'] ?? null)) {
                    $content['items'] = $this->renameItemKeys($content['items'], self::REVIEW_KEYS);
                }

                return ['reviews', $content];

            case 'calendly':
            case 'booking_hero':
                if (array_key_exists('calendly_url', $content)) {
                    $content['url'] ??= $content['calendly_url'];
                    unset($content['calendly_url']);
                }
                $content['layout'] = $type === 'booking_hero' ? 'hero' : 'section';

                return ['booking', $content];

            case 'hero':
                if (! array_key_exists('height', $content)) {
                    $content['height'] = ($content['size'] ?? null) === 'compact' ? 'compact' : 'tall';
                }
                unset($content['size']);

                return ['hero', $content];
        }

        return [$type, $content];
    }

    /** @return array{0: string, 1: array} */
    private function fromCore(string $type, array $content): array
    {
        switch ($type) {
            case 'text':
                return ['rich_text', $content];

            case 'reviews':
                if (is_array($content['items'] ?? null)) {
                    $content['items'] = $this->renameItemKeys($content['items'], array_flip(self::REVIEW_KEYS));
                }

                return ['testimonials', $content];

            case 'booking':
                $oldType = ($content['layout'] ?? 'section') === 'hero' ? 'booking_hero' : 'calendly';
                if (array_key_exists('url', $content)) {
                    $content['calendly_url'] ??= $content['url'];
                    unset($content['url']);
                }
                unset($content['layout']);

                return [$oldType, $content];

            case 'hero':
                if (array_key_exists('height', $content)) {
                    $content['size'] = $content['height'] === 'compact' ? 'compact' : 'default';
                    unset($content['height']);
                }

                return ['hero', $content];
        }

        return [$type, $content];
    }

    private function renameItemKeys(array $items, array $map): array
    {
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($map as $from => $to) {
                if (array_key_exists($from, $item)) {
                    if (! array_key_exists($to, $item)) {
                        $item[$to] = $item[$from];
                    }
                    unset($item[$from]);
                }
            }
            $items[$i] = $item;
        }

        return $items;
    }
};
