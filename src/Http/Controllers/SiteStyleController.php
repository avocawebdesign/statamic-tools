<?php

namespace Avocadesign\StatamicTools\Http\Controllers;

use Avocadesign\StatamicTools\Site\Blocks;
use Avocadesign\StatamicTools\Site\CssTokens;
use Avocadesign\StatamicTools\Site\Samples;
use Avocadesign\StatamicTools\Site\Spacing;
use Illuminate\Http\Response;
use Statamic\Facades\Asset;
use Statamic\Facades\Blink;
use Statamic\Facades\Form;

class SiteStyleController extends SiteController
{
    public function __invoke(): Response
    {
        $this->gate();

        $cfg = config('statamic-tools.site');
        $tokens = CssTokens::fromEntry(base_path($cfg['css_entry']));
        return $this->page('style', [
            'title' => 'Style reference',
            'brand' => $this->brand(),
            'css_files' => $tokens->files(),
            'colour_groups' => $this->asList($tokens->colourGroups()),
            'type_scale' => array_values(array_filter($tokens->typeScale(), fn ($t) => ! in_array($t['name'], $cfg['type_scale_note_only'] ?? [], true))),
            'type_scale_noted' => array_values(array_filter($tokens->typeScale(), fn ($t) => in_array($t['name'], $cfg['type_scale_note_only'] ?? [], true))),
            'typography_tokens' => $tokens->typographyTokens(),
            'fonts' => $tokens->fonts(),
            'weights' => $tokens->weights(),
            'typography_classes' => array_map(fn ($class) => [
                'class' => $class,
                'sample' => match (true) {
                    str_starts_with($class, 'heading-size-') => 'Heading size '.substr($class, 13),
                    $class === 'lede' => 'A lede paragraph, a step above body copy',
                    $class === 'caption' => 'A caption below an image',
                    $class === 'brand-text' => 'Brand-coloured text',
                    default => "Sample text with the .{$class} class",
                },
            ], $cfg['typography_classes']),
            'schemes' => $this->choices($cfg['scheme_fieldset'], 'colour_scheme'),
            'margins' => $this->choices($cfg['margins_fieldset'] ?? $cfg['scheme_fieldset'], 'block_margins'),
            'spacing' => $this->spacing($tokens, $cfg),
            'buttons' => $buttons = $this->buttons($cfg['button_fieldset']),
            // The matrix also shows the open-in-new-tab treatment, once for a button and once inline.
            'button_rows' => [...$buttons, ...$this->newTabRows($buttons)],
            'image_variants' => $this->images($cfg['image_set']),
            'form_fields' => $this->formFields(),
        ]);
    }

    /** The logos and favicons the site and its control panel are configured with. */
    private function brand(): array
    {
        $logo = config('statamic.cp.custom_logo_url');
        $favicons = [];
        foreach (glob(public_path('favicons/*')) ?: [] as $file) {
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['ico', 'png', 'svg', 'webp'], true)) {
                $size = @getimagesize($file);
                $favicons[] = ['url' => '/favicons/'.basename($file), 'name' => basename($file), 'size' => $size ? "{$size[0]}×{$size[1]}" : ''];
            }
        }

        return [
            'cp_nav_logo' => is_array($logo) ? ($logo['nav'] ?? null) : $logo,
            'cp_login_logo' => is_array($logo) ? ($logo['outside'] ?? null) : $logo,
            'cp_dark_logo' => config('statamic.cp.custom_dark_logo_url'),
            'cp_logo_text' => config('statamic.cp.custom_logo_text'),
            'cp_favicon' => config('statamic.cp.custom_favicon_url'),
            'favicons' => $favicons,
        ];
    }

    /** The first button and the first inline button again, with target_blank on. */
    private function newTabRows(array $buttons): array
    {
        $rows = [];
        foreach (['button', 'inline'] as $type) {
            foreach ($buttons as $row) {
                $isInline = ($row['button_type'] ?? 'button') !== 'button';
                if (($type === 'inline') === $isInline) {
                    $rows[] = [...$row, 'target_blank' => true, 'recipe' => $row['recipe'].' · Open in new tab: On'];
                    break;
                }
            }
        }

        return $rows;
    }

    /** ['Palette' => [...]] becomes [['title' => 'Palette', 'tokens' => [...]]] for Antlers loops. */
    private function asList(array $groups): array
    {
        $out = [];
        foreach ($groups as $title => $tokens) {
            $out[] = ['title' => $title, 'tokens' => $tokens];
        }

        return $out;
    }

    /** @return array<int, array{handle: string, label: string}> */
    private function choices(string $fieldset, string $handle): array
    {
        if (! $field = Blocks::field($fieldset, $handle)) {
            return [];
        }
        $out = [];
        foreach (Blocks::options($field) as $key => $label) {
            $out[] = ['handle' => $key, 'label' => $label];
        }

        return $out;
    }

    /** Every combination of the button fieldset's option fields, with a recipe naming the settings. */
    private function buttons(string $fieldset): array
    {
        if (! $set = \Statamic\Facades\Fieldset::find($fieldset)) {
            return [];
        }
        $variantFields = array_filter(
            Blocks::variantFields($set->fields()),
            fn ($v, $handle) => str_starts_with($handle, 'button'),
            ARRAY_FILTER_USE_BOTH
        );

        $combos = [[]];
        foreach ($variantFields as $handle => $variant) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($variant['options'] as $key => $label) {
                    $next[] = [...$combo, $handle => ['key' => $key, 'label' => $label, 'display' => $variant['display']]];
                }
            }
            $combos = $next;
        }

        // The inline button ignores colour and style, so it gets one row, not one per combination.
        $seen = [];
        $combos = array_values(array_filter($combos, function ($combo) use (&$seen) {
            $type = $combo['button_type']['key'] ?? 'button';
            if ($type === 'button') {
                return true;
            }

            return $seen[$type] = ! isset($seen[$type]);
        }));

        $out = [];
        foreach ($combos as $combo) {
            $isInline = ($combo['button_type']['key'] ?? 'button') !== 'button';
            $named = $isInline ? array_filter($combo, fn ($c, $h) => $h === 'button_type', ARRAY_FILTER_USE_BOTH) : $combo;
            $row = ['label' => 'Button label', 'recipe' => implode(' · ', array_map(fn ($c) => "{$c['display']}: {$c['label']}", $named))];
            foreach ($combo as $handle => $c) {
                $row[$handle] = $c['key'] === 'true' ? true : ($c['key'] === 'false' ? false : $c['key']);
            }
            $out[] = $row;
        }

        return $out;
    }

    /** The fluid grid, the content widths, the stack values the templates use, and the section stack between blocks. */
    private function spacing(CssTokens $tokens, array $cfg): array
    {
        $unit = Spacing::unit($tokens);
        $breakpoints = Spacing::breakpoints($tokens);
        $order = ['' => -1, ...array_flip(array_keys($breakpoints))];
        $from = fn (string $prefix) => $prefix === '' ? 'Every width' : Spacing::px((string) ($breakpoints[$prefix] ?? '0')).' and up';
        $size = fn (float $steps) => Spacing::size($steps, $unit);

        $widths = [];
        foreach (['span-full', 'span-content', 'span-xl', 'span-lg', 'span-md'] as $class) {
            if (! $rule = $tokens->rule(".{$class}") ?? $tokens->rule("@utility {$class}")) {
                continue;
            }
            $steps = isset($rule['declarations']['grid-column']) ? [$from('').': '.self::columns($rule['declarations']['grid-column'])] : [];
            foreach ($rule['media'] as $condition => $declarations) {
                if (isset($declarations['grid-column']) && preg_match('/--breakpoint-([a-z0-9]+)/', $condition, $m)) {
                    $steps[] = $from($m[1]).': '.self::columns($declarations['grid-column']);
                }
            }
            $widths[] = ['class' => $class, 'steps' => $steps, 'file' => $rule['file']];
        }

        $section = Spacing::section($sectionFile = resource_path('views/'.($cfg['page_template'] ?? 'default').'.antlers.html'), $tokens);
        $rows = [];
        if ($section) {
            $prefixes = array_unique([...array_keys($section['stack']), ...array_keys($section['padding_bottom'])]);
            usort($prefixes, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
            $between = $after = null;
            foreach ($prefixes as $prefix) {
                // Mobile first: a breakpoint without its own value keeps the one below it.
                $between = $section['stack'][$prefix] ?? $between;
                $after = $section['padding_bottom'][$prefix] ?? $after;
                $rows[] = [
                    'window' => $from((string) $prefix),
                    'between' => $between === null ? '' : implode(' · ', $size($between)),
                    'after' => $after === null ? '' : implode(' · ', $size($after)),
                ];
            }
        }

        // Name each template after the block or set it renders.
        $names = [];
        foreach ([[Blocks::pageBuilder(), 'block'], [Blocks::article(), 'set']] as [$items, $noun]) {
            foreach ($items as $item) {
                if (($item['partial']['origin'] ?? null) === 'site' && $item['partial']['path']) {
                    $names[preg_replace('/\.antlers\.html$/', '', $item['partial']['path'])] = "{$item['display']} {$noun}";
                }
            }
        }
        $grid = $tokens->rule('.fluid-grid');

        return [
            'unit' => $unit,
            'grid' => [
                'gap' => $grid['declarations']['--col-gap'] ?? '',
                'side' => $grid['declarations']['--padding-left'] ?? '',
                'max' => $grid['declarations']['--content-max-width'] ?? '',
                'file' => $grid['file'] ?? '',
            ],
            'widths' => $widths,
            'stacks' => array_map(fn ($s) => [...$s, ...$size($s['steps'])], Spacing::stacks(resource_path('views'), $names, $section, $sectionFile)),
            'section' => $section ? [...$section, 'rows' => $rows] : null,
            'scheme_padding' => $tokens->all()['scheme-block-padding']['value'] ?? '',
        ];
    }

    /** "col-3 / span 8" reads as "8 columns from column 3". */
    private static function columns(string $value): string
    {
        $value = trim($value);

        return match (true) {
            $value === 'full' => 'edge to edge',
            $value === 'content' => 'the content width, 12 columns',
            (bool) preg_match('/^col-(\d+)\s*\/\s*span\s+(\d+)$/', $value, $m) => "{$m[2]} columns from column {$m[1]}",
            default => $value,
        };
    }

    /** The handle of the unsaved form the Form fields section renders. Nothing is ever saved under it. */
    public const FORM_HANDLE = 'style_reference_fields';

    /**
     * One field of every Forms 2 fieldtype, in an unsaved form the page renders through the site's own field views.
     * The config is each fieldtype's own example(), the same one the control panel previews, so a fieldtype Statamic
     * or an addon adds later turns up here without a change. The form is put in Blink under the handle {{ form:create }}
     * looks up, which is how the tag takes a form that was never saved.
     *
     * @return array{handle: string, count: int, from_statamic: int}|null null before Statamic 6.44, which has no Forms 2.
     */
    private function formFields(): ?array
    {
        if (! app()->bound('statamic.form-fieldtypes')) {
            return null;
        }

        // The order the control panel's field picker groups them in.
        $categories = ['text', 'contact', 'choice', 'number', 'rate', 'datetime', 'media', 'structure', 'information'];
        // Four fieldtypes have no example of their own.
        $samples = [
            'heading' => ['display' => 'About your project', 'subheading' => 'A heading inside the form, with an optional subheading.'],
            'paragraph' => ['display' => 'Paragraph', 'text' => "A paragraph inside the form.\nIts line breaks are kept."],
            'spacer' => ['display' => 'Spacer'],
            'group' => ['display' => 'Postal address', 'fields' => [
                ['handle' => 'street', 'field' => ['type' => 'short_answer', 'display' => 'Street', 'validate' => ['required']]],
                ['handle' => 'city', 'field' => ['type' => 'short_answer', 'display' => 'Town or city', 'width' => 50]],
                ['handle' => 'postcode', 'field' => ['type' => 'short_answer', 'display' => 'Postcode', 'width' => 50]],
            ]],
        ];
        // The first text field also shows a required marker and instructions.
        $extras = ['short_answer' => ['validate' => ['required'], 'instructions' => 'Instructions, as an editor writes them under the label.']];

        $rows = collect(app('statamic.form-fieldtypes'))
            ->map(fn (string $class, string $handle) => [$handle, app($class)])
            ->sortBy(fn ($pair) => [
                array_search($pair[1]->categories()[0] ?? 'zz', $categories, true) === false ? 99 : array_search($pair[1]->categories()[0], $categories, true),
                $pair[1]->order() ?? 50,
                $pair[1]->title(),
            ])
            ->values();

        $fields = [];
        $previous = null;
        $fromStatamic = 0;
        foreach ($rows as [$handle, $fieldtype]) {
            $view = $fieldtype->view();
            $path = (string) rescue(fn () => view()->getFinder()->find($view), '', false);
            $isSite = $path !== '' && str_starts_with($path, resource_path());
            $fromStatamic += $isSite ? 0 : 1;
            $category = $fieldtype->categories()[0] ?? 'other';
            $fields[] = ['handle' => $handle, 'field' => [
                'type' => $handle,
                ...($fieldtype->example()['config'] ?? $samples[$handle] ?? ['display' => $fieldtype->title()]),
                ...($extras[$handle] ?? []),
                // Read back by the template: the fieldtype's name, the view that drew it, and where each group starts.
                'sk_title' => $fieldtype->title(),
                'sk_view' => $isSite ? str_replace(base_path().'/', '', $path) : $view,
                'sk_site_view' => $isSite,
                'sk_category' => $category === $previous ? null : ucfirst($category),
            ]];
            $previous = $category;
        }

        Blink::put('form-'.self::FORM_HANDLE, Form::make(self::FORM_HANDLE)->title('Form fields')->formFields(['fields' => $fields]));

        return ['handle' => self::FORM_HANDLE, 'count' => count($fields), 'from_statamic' => $fromStatamic];
    }

    /** The image set rendered with every value of each of its option fields, one factor at a time. */
    private function images(string $setHandle): array
    {
        $set = Blocks::article()[$setHandle] ?? null;
        if (! $set) {
            return [];
        }
        $fields = $set['fields'];
        $base = [...Samples::forFields($fields, ['link_type' => 'none']), 'caption_inline' => false];
        $variants = [['recipe' => 'Defaults', 'values' => $base]];
        // The inline caption sits over the bottom left of the image, as Two Images Offset and Media and text Edge to center use it.
        $variants[] = ['recipe' => 'Inline caption (.caption-inline)', 'values' => [...$base, 'caption_inline' => true]];

        foreach (Blocks::variantFields($fields) as $handle => $variant) {
            foreach ($variant['options'] as $key => $label) {
                if ((string) $base[$handle] === (string) $key) {
                    continue;
                }
                $variants[] = [
                    'recipe' => "{$variant['display']}: {$label}",
                    'values' => [...$base, $handle => $key],
                ];
            }
        }
        $variants[] = ['recipe' => 'With a link', 'values' => [...$base, 'link_type' => 'url', 'link_url' => '#']];

        $out = [];
        foreach ($variants as $variant) {
            $values = $variant['values'];
            // One real image through every variant, so what changes between them is the crop, not the source.
            $path = Samples::placeholder();
            $out[] = [...$values, 'image' => $path ? (Asset::find("images::{$path}") ?? $path) : null, 'recipe' => $variant['recipe']];
        }

        return $out;
    }
}
