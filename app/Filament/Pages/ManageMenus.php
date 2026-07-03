<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\PageLinkField;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\Url;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageMenus extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.manage-menus';

    /**
     * Vaste menu's. `children` = of het Hoofdmenu submenu's mag hebben.
     * Volgorde = volgorde in de admin-UI.
     */
    public const MENUS = [
        'main' => ['name' => 'Hoofdmenu', 'children' => true, 'showTitle' => false],
        'footer_1' => ['name' => 'Footer menu 1', 'children' => false, 'showTitle' => true],
        'footer_2' => ['name' => 'Footer menu 2', 'children' => false, 'showTitle' => true],
        'footer_3' => ['name' => 'Footer menu 3', 'children' => false, 'showTitle' => true],
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'Menu\'s';
    }

    public function getTitle(): string
    {
        return 'Website menu\'s';
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    public function menuForm(Schema $schema): Schema
    {
        return $schema
            ->components(
                collect(self::MENUS)
                    ->map(fn (array $config, string $location) => $this->menuSection($location, $config))
                    ->values()
                    ->all(),
            )
            ->statePath('data');
    }

    protected function menuSection(string $location, array $config): Section
    {
        $components = [];

        if ($config['showTitle']) {
            $components[] = TextInput::make("{$location}.title")
                ->label('Titel')
                ->maxLength(64)
                ->helperText('Wordt als kop boven deze footerkolom getoond (bv. "Ontdekken").');
        }

        $components[] = Repeater::make("{$location}.items")
            ->label('Menu-items')
            ->hiddenLabel()
            ->addActionLabel('Item toevoegen')
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->collapsible()
            ->collapsed()
            ->reorderable()
            ->defaultItems(0)
            ->schema($this->itemSchema($config['children']));

        return Section::make($config['name'])
            ->collapsible()
            ->collapsed()
            ->schema($components);
    }

    /**
     * @return array<int, mixed>
     */
    protected function itemSchema(bool $withChildren): array
    {
        $schema = [
            TextInput::make('label')
                ->label('Label')
                ->required()
                ->maxLength(255),
            PageLinkField::make(false),
            Toggle::make('target_blank')
                ->label('Openen in nieuw tabblad')
                ->inline(false),
        ];

        if ($withChildren) {
            $schema[] = Repeater::make('children')
                ->label('Submenu-items')
                ->addActionLabel('Submenu-item toevoegen')
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->defaultItems(0)
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    PageLinkField::make(false),
                    Toggle::make('target_blank')
                        ->label('Openen in nieuw tabblad')
                        ->inline(false),
                ]);
        }

        return $schema;
    }

    protected function fillForm(): void
    {
        $data = [];

        foreach (self::MENUS as $location => $config) {
            $menu = Menu::firstOrCreate(
                ['location' => $location],
                ['name' => $config['name']],
            );
            $menu->load(['items.children']);

            $data[$location] = [
                'title' => $menu->title,
                'items' => $menu->items
                    ->map(fn (MenuItem $item) => $this->itemToState($item, $config['children']))
                    ->all(),
            ];
        }

        $this->menuForm->fill($data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemToState(MenuItem $item, bool $withChildren): array
    {
        $state = [
            'label' => $item->label,
            'link_type' => $item->page_id ? 'page' : 'url',
            'page_id' => $item->page_id,
            'href' => $item->page_id ? $item->resolvedHref() : $item->url,
            'target_blank' => $item->target_blank,
        ];

        if ($withChildren) {
            $state['children'] = $item->children
                ->map(fn (MenuItem $child) => $this->itemToState($child, false))
                ->all();
        }

        return $state;
    }

    public function save(): void
    {
        $state = $this->menuForm->getState();

        foreach (self::MENUS as $location => $config) {
            $menu = Menu::firstOrCreate(
                ['location' => $location],
                ['name' => $config['name']],
            );

            $menu->update(['title' => $state[$location]['title'] ?? null]);

            // Delete-and-recreate: eenvoudiger en betrouwbaarder dan diffen.
            MenuItem::where('menu_id', $menu->id)->delete();

            foreach ($state[$location]['items'] ?? [] as $position => $item) {
                $parent = $this->createItem($menu->id, null, $position, $item);

                if ($config['children']) {
                    foreach ($item['children'] ?? [] as $childPosition => $child) {
                        $this->createItem($menu->id, $parent->id, $childPosition, $child);
                    }
                }
            }
        }

        Notification::make()
            ->title('Menu\'s bijgewerkt.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function createItem(int $menuId, ?int $parentId, int $position, array $item): MenuItem
    {
        $isPage = ($item['link_type'] ?? 'page') === 'page';

        return MenuItem::create([
            'menu_id' => $menuId,
            'parent_id' => $parentId,
            'label' => $item['label'],
            'page_id' => $isPage ? ($item['page_id'] ?? null) : null,
            'url' => $isPage ? null : Url::normalize($item['href'] ?? null),
            'position' => $position,
            'target_blank' => $item['target_blank'] ?? false,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
            Action::make('view')
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->tooltip('Bekijk op site')
                ->url('/'),
        ];
    }
}
