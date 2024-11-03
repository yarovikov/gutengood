<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Providers;

use Illuminate\Support\ServiceProvider;
use WP_Block_Type_Registry;

use function Roots\asset;


class BlockServiceProvider extends ServiceProvider
{
    /**
     * Blocks directory
     *
     * @var string
     */
    public string $folder = 'Editor/Blocks';

    /**
     * Blocks object
     *
     * @var object
     */
    public object $blocks;

    public function register(): void
    {
        $this->makeInstances();
    }

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        add_action('init', [$this, 'registerBlocks']);
        add_action('init', [$this, 'registerMeta']);
        add_action('rest_api_init', [$this, 'blockEndpoint']);
    }

    /**
     * Make instances of the blocks. Make $blocks object
     *
     * @return void
     */
    public function makeInstances(): void
    {
        $this->blocks = collect();
        collect(glob($this->app->basePath('app/' . $this->folder . '/*.php')))->map(
            function (string $file): void {
                $src = $this->formatFile($this->folder, $file);

                $this->app->bind("block.$src->handle", function () use ($src) {
                    return new $src->class();
                });

                $this->blocks->push("block.$src->handle");
            }
        );
    }

    public function formatFile(string $class, string $file): object
    {
        return (object) [
            'handle' => substr(strtolower(basename(preg_replace('/[A-Z]/', '-$0', $file), '.php')), 1),
            'class' => '\\App\\' . str_replace('/', '\\', $class) . '\\' . basename($file, '.php'),
        ];
    }

    public function registerBlocks(): void
    {
        $blocks = $this->blocks;

        if (empty($blocks)) {
            return;
        }

        foreach ($blocks as $block) {
            if (!WP_Block_Type_Registry::get_instance()->is_registered($this->app[$block]->name)) {
                $this->app[$block]->registerBlockType();
            }
        }
    }

    /**
     * Enqueue the block assets with the block editor and front-end.
     *
     * @return void
     */
    public function enqueue(): void
    {
        $blocks = $this->blocks;

        if (empty($blocks)) {
            return;
        }

        foreach ($blocks as $block) {
            $this->app[$block]->enqueue();
        }
    }

    public function enqueueBlockEditorAssets(): void
    {
        $blocks = $this->blocks;

        if (empty($blocks)) {
            return;
        }

        $gutengood_blocks = [];

        foreach ($blocks as $block) {
            if (false === $this->app[$block]->editor_script) {
                $gutengood_blocks[] = (object) [
                    'title' => $this->app[$block]->title,
                    'name' => $this->app[$block]->name,
                    'description' => $this->app[$block]->description,
                    'icon' => $this->getIcon($this->app[$block]->icon),
                    'category' => $this->app[$block]->category,
                ];
            }
        }

        if (empty($gutengood_blocks)) {
            return;
        }

        wp_localize_script('editor', 'gutengoodBlocks', $gutengood_blocks);
    }

    public function blockEndpoint(): void
    {
        $blocks = $this->blocks;

        if (empty($blocks)) {
            return;
        }

        foreach ($blocks as $block) {
            $this->app[$block]->blockEndpoint();
        }
    }

    /**
     * Register post meta for using in blocks (optional)
     *
     * @return void
     */
    public function registerMeta(): void
    {
        $blocks = $this->blocks;

        if (empty($blocks)) {
            return;
        }

        foreach ($blocks as $block) {
            array_map(function (array $meta): void {
                if (empty($meta) || empty($meta['post_type']) || empty($meta['meta_key'])) {
                    return;
                }

                register_meta(
                    $meta['post_type'],
                    $meta['meta_key'],
                    [
                        'show_in_rest' => true,
                        'single' => true,
                        'type' => $meta['type'],
                        'default' => $meta['default'],
                    ]
                );
            }, $this->app[$block]->blockMeta());
        }
    }

    /**
     * Retrieve the block icon.
     *
     * @param $icon
     *
     * @return string
     */
    public function getIcon($icon): string
    {
        if ( str_contains( $icon, '<svg' ) ) {
            $xml = simplexml_load_string($icon);
            $svg_obj = ['svg' => $xml];

            return json_encode($svg_obj);
        }

        return $icon;
    }
}
