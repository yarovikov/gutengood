<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Editor;

use Illuminate\View\View;

use WP_Block_Type_Registry;
use WP_Post;
use function Roots\bundle;

class AbstractBlock
{
    public const MARGIN_TOP_DESKTOP = 0;
    public const MARGIN_BOTTOM_DESKTOP = 0;
    public const MARGIN_TOP_MOBILE = 0;
    public const MARGIN_BOTTOM_MOBILE = 0;

    /**
     * Block title
     *
     * @var string
     */
    public string $title = '';

    /**
     * Block mame
     *
     * @var string
     */
    public string $name = '';

    /**
     * Block view
     *
     * @var string
     */
    public string $view = '';

    /**
     * Block editor js if you want to use own block js
     * By default used editor/block-register.js for all blocks
     *
     * @var bool
     */
    public bool $editor_script = false;

    public function registerBlockType(): void
    {
        $attributes = $this->getAttributes();

        register_block_type($this->name, [
            'attributes' => [...$attributes],
            'render_callback' => fn(array $attributes, string $content): View => view($this->view, $this->getBlockData($attributes, $content)),
        ]);
    }

    /**
     * Block attributes
     *
     * Types: null, boolean, object, array, string, integer
     * @var array
     */
    public function getAttributes(): array
    {
        $attributes = [];

        $fields_and_options = [
            ...$this->fields(),
            ...$this->options(),
            ...$this->defaultOptions(),
        ];

        if (empty($fields_and_options)) {
            return [];
        }

        foreach ($fields_and_options as $field_or_option) {
            $attributes[$field_or_option['name']] = match ($field_or_option['type']) {
                'Text', 'Textarea', 'Select', 'ColorPalette', 'RichText' => [
                    'type' => 'string',
                    'default' => (string) ($field_or_option['value'] ?? ''),
                ],
                'Image', 'Range' => [
                    'type' => 'integer',
                    'default' => (int) ($field_or_option['value'] ?? ''),
                ],
                'Toggle' => [
                    'type' => 'boolean',
                    'default' => (bool) ($field_or_option['value'] ?? ''),
                ],
                'Gallery' => [
                    'type' => 'array',
                    'default' => array_filter((array) ($field_or_option['value'] ?? [])),
                ],
                'Repeater' => [
                    'type' => 'array',
                    'default' => array_map(function (array $item): array {
                        $item['id'] = substr(hash('sha256', uniqid((string) random_int(1000000000000, 9999999999999), true)), 0, 13);
                        return $item;
                    }, array_filter((array) ($field_or_option['value'] ?? []))),
                ],
                default => null,
            };
        }

        return array_filter($attributes);
    }

    /**
     * Block data
     *
     * @param array $attributes
     * @param string $content
     *
     * @return array
     */
    public function getBlockData(array $attributes, string $content): array
    {
        $block_id = uniqid((str_replace(['/', '-'], '_', $this->name)) . '_');

        return [
            'name' => $this->name,
            'block_class' => 'gutengood-block' . (!empty($attributes['className']) ? ' ' . $attributes['className'] : ''),
            'block_id' => $block_id,
            'block_styles' => $this->getBlockStyles($attributes, $block_id),
            'content' => $content,
            'is_editor' => $this->checkIfTheEditor(),
            'attributes' => (object) $attributes,
        ];
    }

    /**
     * Enqueue js/css block
     *
     * @return void
     */
    public function enqueue(): void
    {
        $assets = $this->getAssets();

        if (empty($assets)) {
            return;
        }

        $post = get_post();
        if (!$post instanceof WP_Post) {
            return;
        }

        $content = (string) apply_filters('content_with_gutengood_blocks', $post->post_content);

        $blocks = parse_blocks($content);
        if (empty($blocks)) {
            return;
        }

        $this->enqueueAssets($blocks, $assets);
    }

    public function enqueueAssets(array $blocks, array $assets): void
    {
        foreach ($blocks as $block) {
            if ($this->name === $block['blockName']) {
                // Get default attributes
                $default_attributes = $this->getBlockDefaultAttributes($block['blockName']);
                // Merge default and changed attributes
                $block['attrs'] = [...$default_attributes, ...$block['attrs'] ?? []];

                array_map(function (array $asset) use ($block): void {
                    if (empty($asset['condition']) || (is_callable($asset['condition']) && $asset['condition']($block))) {
                        if (!empty($asset['dependencies']) && false === is_admin()) {
                            bundle($asset['handle'])->enqueueJs(true, $asset['dependencies']);
                        } else {
                            bundle($asset['handle'])->enqueue();
                        }
                    }
                }, $assets);
            }

            // Check if there are inner blocks
            if (!empty($block['innerBlocks'])) {
                $this->enqueueAssets($block['innerBlocks'], $assets);
            }
        }
    }

    private function getBlockDefaultAttributes(string $block_name): array
    {
        $block_registry = WP_Block_Type_Registry::get_instance();
        $block_type = $block_registry->get_registered($block_name);

        if ($block_type && isset($block_type->attributes)) {
            return array_map(function (array $attribute): mixed {
                return isset($attribute['default']) ? $attribute['default'] : null;
            }, $block_type->attributes);
        }

        return [];
    }

    public function blockEndpoint(): void
    {
        register_rest_route("{$this->name}/v1", '/data', [
            'methods' => 'GET',
            'callback' => fn(): array => $this->blockData(),
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Pass php data to js (used rest api for fetch data in the editor)
     *
     * @return array
     */
    public function blockData(): array
    {
        return [
            'options' => [
                ...$this->options(),
                ...$this->defaultOptions(),
            ],
            'fields' => [
                ...$this->fields(),
            ],
        ];
    }

    /**
     * Available components see here resources/scripts/editor/components/block-options.js
     *
     * @return array
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Available components see here resources/scripts/editor/components/block-options.js
     *
     * @return array
     */
    public function options(): array
    {
        return [];
    }

    public function defaultOptions(): array
    {
        return [
            [
                'name' => 'margin_top_desktop',
                'type' => 'Text',
                'label' => 'Margin Top Desktop',
                'value' => static::MARGIN_TOP_DESKTOP,
            ],
            [
                'name' => 'margin_top_mobile',
                'type' => 'Text',
                'label' => 'Margin Top Mobile',
                'value' => static::MARGIN_TOP_MOBILE,
            ],
            [
                'name' => 'margin_bottom_desktop',
                'type' => 'Text',
                'label' => 'Margin Bottom Desktop',
                'value' => static::MARGIN_BOTTOM_DESKTOP,
            ],
            [
                'name' => 'margin_bottom_mobile',
                'type' => 'Text',
                'label' => 'Margin Bottom Mobile',
                'value' => static::MARGIN_BOTTOM_MOBILE,
            ],
        ];
    }

    /**
     * Block meta if needed
     *
     * @return array
     */
    public function blockMeta(): array
    {
        return [
            [
                'post_type' => 'post',
                'meta_key' => '',
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    /**
     * Check we are in the editor or front-end
     *
     * @return bool
     */
    public function checkIfTheEditor(): bool
    {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Js/css block assets. Use bud config
     *
     *
     * @return array
     */
    public function getAssets(): array
    {
        return [];
    }

    /**
     * Default inline block styles
     *
     * @return string
     */
    public function getBlockStyles(array $attributes, string $block_id): string
    {
        $margins = [
            '--block-margin-top-desktop' => (int) ($attributes['margin_top_desktop'] ?? 0),
            '--block-margin-bottom-desktop' => (int) ($attributes['margin_bottom_desktop'] ?? 0),
            '--block-margin-top-mobile' => (int) ($attributes['margin_top_mobile'] ?? 0),
            '--block-margin-bottom-mobile' => (int) ($attributes['margin_bottom_mobile'] ?? 0),
        ];

        $styles = implode(';', array_map(fn($key, $value) => "{$key}:{$value}px", array_keys($margins), $margins));
        $styles = "<style>#{$block_id}{{$styles}}</style>";

        return wp_kses($styles, ['style' => []]);
    }
}
