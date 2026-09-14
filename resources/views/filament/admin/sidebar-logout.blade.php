{{--
    Uitlogknop onderaan de zijbalk (render hook SIDEBAR_FOOTER). Gebruikt de
    Filament-klassen van een gewoon navigatie-item zodat hij er identiek uitziet
    (icoon + label, hover-achtergrond). Padding inline: dit staat buiten
    .fi-sidebar-nav en de app-Tailwind wordt niet in de admin geladen.
--}}
@if (filament()->auth()->check())
    <div style="padding: 0 1rem 1rem;">
        <ul class="fi-sidebar-nav-groups" style="margin: 0;">
            <li class="fi-sidebar-item">
                <form method="post" action="{{ filament()->getLogoutUrl() }}">
                    @csrf
                    <button
                        type="submit"
                        class="fi-sidebar-item-btn"
                        style="width: 100%; cursor: pointer; text-align: start;"
                    >
                        <x-filament::icon
                            icon="heroicon-o-arrow-right-start-on-rectangle"
                            class="fi-sidebar-item-icon"
                        />
                        <span class="fi-sidebar-item-label">Uitloggen</span>
                    </button>
                </form>
            </li>
        </ul>
    </div>
@endif
