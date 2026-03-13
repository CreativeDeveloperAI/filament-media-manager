<div class="mt-4 w-full">
    <x-filament::pagination :paginator="$paginator" :page-options="[10, 25, 50, 100, 'all']"
                            current-page-option-property="perPage"
                            :extreme-links="true" />
</div>
