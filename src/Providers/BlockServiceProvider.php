<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Providers;

use Illuminate\Support\ServiceProvider;

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
        $this->registerBlocks();
    }

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
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

                $this->app->bind($src->handle, function () use ($src) {
                    return new $src->class();
                });

                $this->blocks->push($src->handle);
            }
        );
    }

    public function formatFile(string $class, string $file): object
    {
        return (object) [
            'handle' => strtolower($class) . '.' . strtolower(basename(preg_replace('/[A-Z]/', '-$0', $file), '.php')),
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
            $this->app[$block]->registerBlockType();
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
}
