{{--
    Widget-host. De widgets bepalen zelf hun columnSpan ('full'), dus één
    kolom volstaat hier; wie ze naast elkaar wil, zet columnSpan op de widget.
--}}
<x-filament-panels::page>
    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :columns="1"
    />
</x-filament-panels::page>
