<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;

class RoomNestedContent
{
    public static function prepare(array $data): array
    {
        $data = self::trimStrings($data);

        foreach (['amenities', 'highlight_tags'] as $field) {
            if (is_array($data[$field] ?? null)) {
                $data[$field] = array_values($data[$field]);
            }
        }

        if (! is_array($data['amenities'] ?? null)) {
            return $data;
        }

        foreach ($data['amenities'] as &$group) {
            if (! is_array($group) || ! is_array($group['items'] ?? null)) {
                continue;
            }

            $group['items'] = array_values($group['items']);

            foreach ($group['items'] as &$item) {
                if (is_array($item) && self::emptyPair($item['value'] ?? null)) {
                    $item['value'] = null;
                }
            }
            unset($item);
        }
        unset($group);

        return $data;
    }

    public static function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'amenities' => ['nullable', 'array', 'list'],
            'amenities.*' => ['required', 'array:group,items'],
            'amenities.*.group' => ['required', 'array:id,en'],
            'amenities.*.group.id' => ['required', 'string', 'max:100'],
            'amenities.*.group.en' => ['required', 'string', 'max:100'],
            'amenities.*.items' => ['required', 'array', 'list', 'min:1'],
            'amenities.*.items.*' => ['required', 'array:name,value,show_in_comparison'],
            'amenities.*.items.*.name' => ['required', 'array:id,en'],
            'amenities.*.items.*.name.id' => ['required', 'string', 'max:100'],
            'amenities.*.items.*.name.en' => ['required', 'string', 'max:100'],
            'amenities.*.items.*.value' => ['nullable', 'array:id,en'],
            'amenities.*.items.*.value.id' => ['required_with:amenities.*.items.*.value', 'nullable', 'string', 'max:255'],
            'amenities.*.items.*.value.en' => ['required_with:amenities.*.items.*.value', 'nullable', 'string', 'max:255'],
            'amenities.*.items.*.show_in_comparison' => ['required', 'boolean'],
            'highlight_tags' => ['nullable', 'array', 'list'],
            'highlight_tags.*' => ['required', 'array:icon,label'],
            'highlight_tags.*.icon' => ['required', 'string', 'max:100'],
            'highlight_tags.*.label' => ['required', 'array:id,en'],
            'highlight_tags.*.label.id' => ['required', 'string', 'max:100'],
            'highlight_tags.*.label.en' => ['required', 'string', 'max:100'],
            'whatsapp_text' => ['nullable', 'array:id,en'],
            'whatsapp_text.id' => ['required_with:whatsapp_text', 'string', 'max:255'],
            'whatsapp_text.en' => ['required_with:whatsapp_text', 'string', 'max:255'],
        ]);
    }

    private static function trimStrings(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(str_replace("\xc2\xa0", ' ', $value));
        }

        return is_array($value) ? array_map(self::trimStrings(...), $value) : $value;
    }

    private static function emptyPair(mixed $value): bool
    {
        return $value === null || (is_array($value) && array_diff(array_keys($value), ['id', 'en']) === []
            && in_array($value['id'] ?? null, [null, ''], true) && in_array($value['en'] ?? null, [null, ''], true));
    }

    /** Project only display text; shared order, flags, and icons remain identical. */
    public static function amenities(?array $groups, string $locale): array
    {
        return array_map(static fn (array $group): array => [
            'group' => $group['group'][$locale] ?? '',
            'items' => array_map(static fn (array $item): array => [
                'name' => $item['name'][$locale] ?? '',
                'value' => $item['value'][$locale] ?? null,
                'show_in_comparison' => (bool) $item['show_in_comparison'],
            ], $group['items']),
        ], $groups ?? []);
    }

    public static function highlightTags(?array $tags, string $locale): array
    {
        return array_map(static fn (array $tag): array => [
            'icon' => $tag['icon'],
            'label' => $tag['label'][$locale] ?? '',
        ], $tags ?? []);
    }
}
