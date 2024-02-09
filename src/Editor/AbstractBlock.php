<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Editor;

use Illuminate\View\View;

use WP_Post;
use function Roots\bundle;

class AbstractBlock
{
    /**
     * Block mame. Must match the name in js
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
     * Js/css block dependencies. Use bud config
     *
     * @var array
     */
    public array $dependencies = [];

    /**
     * Block attributes
     *
     * Types: null, boolean, object, array, string, integer
     * @var array
     */
    public array $attributes = [];

    public function registerBlockType(): void
    {
        register_block_type($this->name, [
            'attributes' => $this->attributes,
            'render_callback' => fn(array $attributes, string $content): View => view($this->view, $this->getBlockData($attributes, $content)),
        ]);
    }

    /**
     * Block data
     *
     * @param array  $attributes
     * @param string $content
     *
     * @return array
     */
    public function getBlockData(array $attributes, string $content): array
    {
        return [
            'name' => $this->name,
            'block_class' => $attributes['className'] ?? '',
            'block_id' => uniqid((str_replace(['/', '-'], '_', $this->name)) . '_'),
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
        $dependencies = $this->dependencies;

        if (empty($dependencies)) {
            return;
        }

        $post = get_post();
        if (!$post instanceof WP_Post) {
            return;
        }

        $blocks = parse_blocks($post->post_content);
        if (empty($blocks)) {
            return;
        }

        foreach ($blocks as $block) {
            if ($this->name === $block['blockName']) {
                array_map(function (string $dependency): void {
                    bundle($dependency)->enqueue();
                }, $dependencies);
            }
        }
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
     * Pass php data to js
     *
     * @return array
     */
    public function blockData(): array
    {
        return [
            'options' => $this->options(),
            'data' => $this->data(),
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function data(): array
    {
        return [];
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
}
