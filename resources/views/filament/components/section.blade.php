@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\Enums\IconSize;
    use Filament\Support\View\Components\SectionComponent\IconComponent;
    use function Filament\Support\is_slot_empty;

    $afterHeader = $getChildSchema($schemaComponent::AFTER_HEADER_SCHEMA_KEY)?->toHtmlString();
    $beforeHeader = $getChildSchema($schemaComponent::BEFORE_HEADER_SCHEMA_KEY)?->toHtmlString();
    $headingContent = $getChildSchema($schemaComponent::HEADING_CONTENT_SCHEMA_KEY)?->toHtmlString();
    $isAside = $isAside();
    $isCollapsed = $isCollapsed();
    $isCollapsible = $isCollapsible();
    $isCompact = $isCompact();
    $isContained = $isContained();
    $isDivided = $isDivided();
    $isFormBefore = $isFormBefore();
    $description = $getDescription();
    $footer = $getChildSchema($schemaComponent::FOOTER_SCHEMA_KEY)?->toHtmlString();
    $heading = $getHeading();
    $headingTag = $getHeadingTag();
    $icon = $getIcon();
    $iconColor = $getIconColor();
    $iconSize = $getIconSize();
    $shouldPersistCollapsed = $shouldPersistCollapsed();
    $isSecondary = $isSecondary();
    $id = $getId();

    if (filled($iconSize) && !$iconSize instanceof IconSize) {
        $iconSize = IconSize::tryFrom($iconSize) ?? $iconSize;
    }

    $hasDescription = filled((string) $description);
    $hasHeading = filled($heading) || !is_slot_empty($headingContent);
    $hasIcon = filled($icon);
    $hasHeader =
        $hasIcon ||
        $hasHeading ||
        $hasDescription ||
        $collapsible ||
        !is_slot_empty($afterHeader) ||
        !is_slot_empty($beforeHeader);
@endphp

<div
    {{ $attributes->merge(
            [
                'id' => $id,
            ],
            escape: false,
        )->merge($getExtraAttributes(), escape: false)->merge($getExtraAlpineAttributes(), escape: false)->class(['fi-sc-section']) }}>
    @if (filled($label = $getLabel()))
        <div class="fi-sc-section-label-ctn">
            {{ $getChildSchema($schemaComponent::BEFORE_LABEL_SCHEMA_KEY) }}

            <div class="fi-sc-section-label">
                {{ $label }}
            </div>

            {{ $getChildSchema($schemaComponent::AFTER_LABEL_SCHEMA_KEY) }}
        </div>
    @endif

    @if ($aboveContentContainer = $getChildSchema($schemaComponent::ABOVE_CONTENT_SCHEMA_KEY)?->toHtmlString())
        {{ $aboveContentContainer }}
    @endif

    <section {{-- TODO: Investigate Livewire bug - https://github.com/filamentphp/filament/pull/8511 --}} x-data="{
        isCollapsed: @if ($shouldPersistCollapsed) $persist(@js($isCollapsed)).as(`section-${@js($id) ?? $el.id}-isCollapsed`) @else @js($isCollapsed) @endif,
    }"
        @if ($isCollapsible) x-on:collapse-section.window="if ($event.detail.id == @js($id) ?? $el.id) isCollapsed = true"
            x-on:expand="isCollapsed = false"
            x-on:expand-section.window="if ($event.detail.id == @js($id) ?? $el.id) isCollapsed = false"
            x-on:open-section.window="if ($event.detail.id == @js($id) ?? $el.id) isCollapsed = false"
            x-on:toggle-section.window="if ($event.detail.id == @js($id) ?? $el.id) isCollapsed = ! isCollapsed"
            x-bind:class="isCollapsed && 'fi-collapsed'" @endif
        class="{{ \Illuminate\Support\Arr::toCssClasses([
            'fi-section',
            'fi-section-not-contained' => !$isContained,
            'fi-section-has-content-before' => $isFormBefore,
            'fi-section-has-header' => $hasHeader,
            'fi-aside' => $isAside,
            'fi-compact' => $isCompact,
            'fi-collapsible' => $isCollapsible,
            'fi-divided' => $isDivided,
            'fi-secondary' => $isSecondary,
        ]) }}">
        @if ($hasHeader)
            <header @if ($isCollapsible) x-on:click="isCollapsed = ! isCollapsed" @endif
                class="fi-section-header">
                @if (!is_slot_empty($beforeHeader))
                    <div x-on:click.stop class="fi-section-header-before-ctn">
                        {{ $beforeHeader }}
                    </div>
                @endif

                {{ \Filament\Support\generate_icon_html(
                    $icon,
                    attributes: (new \Illuminate\View\ComponentAttributeBag)->color(IconComponent::class, $iconColor),
                    size: $iconSize ?? IconSize::Large,
                ) }}

                @if ($hasHeading || $hasDescription)
                    <div class="fi-section-header-text-ctn">
                        @if ($hasHeading)
                            <{{ $headingTag }} class="fi-section-header-heading">
                                @if (!is_slot_empty($headingContent))
                                    {{ $headingContent }}
                                @else
                                    {{ $heading }}
                                @endif
                                </{{ $headingTag }}>
                        @endif

                        @if ($hasDescription)
                            <p class="fi-section-header-description">
                                {{ $description }}
                            </p>
                        @endif
                    </div>
                @endif

                @if (!is_slot_empty($afterHeader))
                    <div x-on:click.stop class="fi-section-header-after-ctn">
                        {{ $afterHeader }}
                    </div>
                @endif

                @if ($isCollapsible && !$isAside)
                    <x-filament::icon-button color="gray" :icon="\Filament\Support\Icons\Heroicon::ChevronUp" :icon-alias="\Filament\Support\View\SupportIconAlias::SECTION_COLLAPSE_BUTTON"
                        x-on:click.stop="isCollapsed = ! isCollapsed" class="fi-section-collapse-btn" />
                @endif
            </header>
        @endif

        <div @if ($isCollapsible) x-bind:aria-expanded="(! isCollapsed).toString()"
                @if ($isCollapsed || $shouldPersistCollapsed)
                    x-cloak @endif
            @endif
            class="fi-section-content-ctn"
            >
            <div class="fi-section-content">
                {{ $getChildSchema()->extraAttributes(['class' => 'fi-section-content']) }}
            </div>

            @if (!is_slot_empty($footer))
                <footer class="fi-section-footer">
                    {{ $footer }}
                </footer>
            @endif
        </div>
    </section>

    @if ($belowContentContainer = $getChildSchema($schemaComponent::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString())
        {{ $belowContentContainer }}
    @endif
</div>
