<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Traits;

trait Helpers
{
    /**
     * Retrieve the icon.
     *
     * @param $icon
     *
     * @return string
     */
    public function getIcon($icon): string
    {
        if (str_contains($icon, '<svg')) {
            $xml = simplexml_load_string($icon);
            return $xml ? json_encode(['svg' => $xml]) : $icon;
        }
        return $icon;
    }

    public function formatFile(string $class, string $file): object
    {
        return (object) [
            'handle' => substr(strtolower(basename(preg_replace('/[A-Z]/', '-$0', $file), '.php')), 1),
            'class' => '\\App\\' . str_replace('/', '\\', str_replace('.php', '', str_replace('/app/', '', strstr($file, '/app/Editor/')))),
        ];
    }

    /**
     * Attributes
     *
     * Types: null, boolean, object, array, string, integer
     * @var array
     */
    public function getAttributes(array $fields_and_options): array
    {
        if (empty($fields_and_options)) {
            return [];
        }

        $attributes = [];

        foreach ($fields_and_options as $field_or_option) {
            if ('Section' === ($field_or_option['type'] ?? '')) {
                foreach ($field_or_option['fields'] as $section_field_or_option) {
                    $attributes[$section_field_or_option['name']] = $this->getDefaultAttribute($section_field_or_option['type'], $section_field_or_option['value'] ?? '');
                }
            } else {
                $attributes[$field_or_option['name']] = $this->getDefaultAttribute($field_or_option['type'], $field_or_option['value'] ?? '');
            }
        }

        return array_filter($attributes);
    }

    public function getDefaultAttribute(string $type, mixed $value): ?array
    {
        return match ($type) {
            'TimePicker', 'Text', 'Textarea', 'Select', 'ColorPalette', 'ColorPicker', 'RichText' => [
                'type' => 'string',
                'default' => (string) ($value ?? ''),
            ],
            'Image', 'Range' => [
                'type' => 'integer',
                'default' => (int) ($value ?? ''),
            ],
            'Toggle' => [
                'type' => 'boolean',
                'default' => (bool) ($value ?? ''),
            ],
            'File', 'Link' => [
                'type' => 'object',
                'default' => !empty($value) ? (object) $value : (object) [],
            ],
            'Gallery' => [
                'type' => 'array',
                'default' => array_filter((array) ($value ?? [])),
            ],
            'Repeater' => [
                'type' => 'array',
                'default' => array_map(function (array $item): array {
                    $item['id'] = substr(hash('sha256', uniqid((string) random_int(1000000000000, 9999999999999), true)), 0, 13);
                    return $item;
                }, array_filter((array) ($value ?? []))),
            ],
            default => null,
        };
    }

    /**
     * Build meta args scheme for Repeater
     *
     * @return array
     */
    public function buildMetaArgs(array $component): array
    {
        $args = [
            'meta_key' => $component['name'],
            ...$this->getDefaultAttribute($component['type'], $component['default'] ?? ''),
        ];

        if ('File' === $component['type']) {
            $args ['show_in_rest'] = [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                        ],
                        'url' => [
                            'type' => 'string',
                        ],
                        'name' => [
                            'type' => 'string',
                        ],
                        'size' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ];
        }

        if ('Link' === $component['type']) {
            $args ['show_in_rest'] = [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                        ],
                        'url' => [
                            'type' => 'string',
                        ],
                        'title' => [
                            'type' => 'string',
                        ],
                        'type' => [
                            'type' => 'string',
                        ],
                        'kind' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ];
        }

        if ('Repeater' === $component['type'] && !empty($component['fields'])) {
            $args ['show_in_rest'] = [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                            ],
                            ...array_reduce($component['fields'], function (array $carry, array $item): array {
                                $carry[$item['name']] = $this->buildMetaArgs($item);
                                return $carry;
                            }, []),
                        ],
                    ],
                ],
            ];
        }

        return $args;
    }

    public function getAssetUri(string $name, string $asset_type_extension = 'js'): string
    {
        if (true === app('is_vite')) {
            $name = "resources/js/blocks/$name";
        }

        $path = "$name.$asset_type_extension";

        return asset($path)->exists() ? asset($path)->uri() : '';
    }
}