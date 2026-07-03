<style>
    .fi-fo-builder-item-header-label {
        font-size: 1.125rem;
        font-weight: 600;
    }
    .fi-fo-builder-item-header {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    /* ——————————————————————————————————————————————————————
       Fixed page-header op edit-record pagina's.
       Vanaf scroll=0 staat de header al op zijn plek (4rem
       onder de Filament-topbar) en blijft daar. Alleen de
       content (tabs + form) scrollt eronderdoor.

       Filament's default .fi-page-header-main-ctn heeft py-8;
       die padding-top moet weg zodat de header direct op
       top: 4rem aansluit (anders eerst 2rem meebewegen).
       —————————————————————————————————————————————————————— */
    .fi-resource-edit-record-page .fi-page-header-main-ctn {
        padding-top: 0;
    }

    .fi-resource-edit-record-page header.fi-header {
        position: sticky;
        top: 4rem; /* exacte hoogte van de Filament-topbar (min-h-16) */
        z-index: 25;
        background-color: rgb(249 250 251); /* fi-color-gray-50 — page-bg light */
        padding-block: 1.25rem;
        margin-block-end: 0.5rem;
    }

    .dark .fi-resource-edit-record-page header.fi-header {
        background-color: rgb(24 24 27); /* fi-color-gray-950 — page-bg dark */
    }
</style>

<script>
(function () {
    document.addEventListener('click', function (e) {
        const header = e.target.closest('.fi-fo-builder-item-header');
        if (!header) return;

        const actionLi = e.target.closest(
            '.fi-fo-builder-item-header-start-actions, .fi-fo-builder-item-header-end-actions'
        );
        const chevron = e.target.closest('.fi-fo-builder-item-header-collapsible-actions');
        if (actionLi && !chevron) return;

        const item = header.closest('.fi-fo-builder-item');
        const builder = item?.closest('.fi-fo-builder');
        if (!item || !builder) return;

        if (!item.classList.contains('fi-collapsed')) return;

        builder.querySelectorAll('.fi-fo-builder-item').forEach(function (other) {
            if (other === item) return;
            if (other.closest('.fi-fo-builder') !== builder) return;
            if (other.classList.contains('fi-collapsed')) return;

            const data = window.Alpine?.$data(other);
            if (data && 'isCollapsed' in data) {
                data.isCollapsed = true;
            }
        });
    }, true);
})();
</script>
