<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'contact'],
            [
                'title'            => 'Contact',
                'is_homepage'      => false,
                'published'        => true,
                'meta_title'       => 'Contact | De Webgoeroe',
                'meta_description' => 'Neem contact op met De Webgoeroe. Stel je vraag of vertel ons over jouw project — we antwoorden binnen 24 uur.',
            ],
        );

        $page->sections()->delete();

        $sections = [

            // 1. Hero -------------------------------------------------------
            [
                'section_type' => 'hero',
                'content' => [
                    'section_id' => null,
                    'size'       => 'compact',
                    'eyebrow'    => 'Contacteer ons',
                    'heading'    => "Stel je vraag.\nWe helpen je graag verder.",
                    'subtitle'   => '<p>Of het nu gaat om een nieuwe website, een AI-assistent of gewoon een eerste kennismaking — stuur ons een bericht en we komen snel bij je terug.</p>',
                    'image'      => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
                    'ctas'       => [],
                ],
            ],

            // 2. Formulier --------------------------------------------------
            [
                'section_type' => 'form',
                'content' => [
                    'section_id'  => null,
                    'background'  => 'light',
                    'eyebrow'     => null,
                    'heading'     => 'Stuur ons een bericht',
                    'intro'       => null,
                    'form_type'   => 'contact',
                    'form_layout' => 'right',
                ],
            ],
        ];

        foreach ($sections as $position => $section) {
            $page->sections()->create([
                'section_type' => $section['section_type'],
                'position'     => $position,
                'locale'       => 'nl',
                'content'      => $section['content'],
            ]);
        }

        $this->command->info('Contactpagina: ' . count($sections) . ' sectie(s) aangemaakt.');
    }
}
