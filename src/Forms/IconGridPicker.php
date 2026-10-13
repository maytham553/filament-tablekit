<?php

namespace TableKit\Forms;

use Closure;
use Filament\Support\Facades\FilamentIcon;

class IconGridPicker extends GridPicker
{
    protected string|Closure|null $noneLabel = null;

    /**
     * @param  array<int, string>|Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        return $this->keys($icons);
    }

    public function noneLabel(string|Closure|null $label): static
    {
        $this->noneLabel = $label;

        return $this;
    }

    public function getNoneLabel(): string
    {
        return (string) ($this->evaluate($this->noneLabel) ?? __('tablekit::quick-save.fields.no_icon'));
    }

    public function getKind(): string
    {
        return 'icon';
    }

    public function isSearchable(): bool
    {
        return true;
    }

    public function getItems(): array
    {
        $none = $this->getNoneLabel();

        $items = [[
            'value' => '',
            'label' => $none,
            'search' => mb_strtolower($none.' none'),
            'style' => null,
            'icon' => null,
            'none' => true,
        ]];

        foreach ($this->getKeys() as $key) {
            $icon = FilamentIcon::resolve("tablekit::icons.{$key}");

            if ($icon === null) {
                continue;
            }

            $label = static::labelFor($key);

            $items[] = [
                'value' => $key,
                'label' => $label,
                'search' => static::searchText($key, $label),
                'style' => null,
                'icon' => $icon,
                'none' => false,
            ];
        }

        return $items;
    }

    public static function labelFor(string $key): string
    {
        $translationKey = "tablekit::icons.{$key}";
        $label = __($translationKey);

        return $label === $translationKey ? ucfirst(str_replace('-', ' ', $key)) : $label;
    }

    /**
     * The key, the label and the keywords in the current locale and in English, so searching in English works in every locale.
     */
    protected static function searchText(string $key, string $label): string
    {
        $parts = [str_replace('-', ' ', $key), $label];

        foreach (array_unique([app()->getLocale(), 'en']) as $locale) {
            foreach (["tablekit::icons.{$key}", "tablekit::icon-keywords.{$key}"] as $translationKey) {
                $text = __($translationKey, [], $locale);

                if ($text !== $translationKey) {
                    $parts[] = $text;
                }
            }
        }

        return mb_strtolower(implode(' ', array_unique($parts)));
    }
}
