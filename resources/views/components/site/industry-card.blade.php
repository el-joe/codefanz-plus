{{-- Figma "Card/Industry": accent icon tile, name and typical solutions. Links to the industry on the Industries page. --}}
@props(['industry', 'headingTag' => 'h3'])

@php($translation = $industry->exactTranslation(app()->getLocale()))

<x-site.tile-card {{ $attributes }} :icon="$industry->icon ?? 'briefcase'" :title="$translation->name" :heading-tag="$headingTag"
                  :text="$translation->typical_solutions ? implode(', ', array_slice($translation->typical_solutions, 0, 3)) : null"
                  :href="route('industries.index').'#'.$translation->slug" />
