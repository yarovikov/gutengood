<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Providers;

use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Yarovikov\Gutengood\Traits\Helpers;

use function Roots\asset;

class PanelServiceProvider extends ServiceProvider
{
    use Helpers;

    /**
     * Panels directory
     *
     * @var string
     */
    public string $folder = 'Editor/Panels';

    /**
     * Panels object
     *
     * @var object
     */
    public object $panels;

    public function register(): void
    {
        $this->makeInstances();
    }

    public function boot(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        add_action('init', [$this, 'registerMeta']);
        add_action('rest_api_init', [$this, 'panelEndpoint']);
    }

    /**
     * Make instances of the panels. Make $panels object
     *
     * @return void
     */
    public function makeInstances(): void
    {
        $this->panels = collect();

        $directory = $this->app->basePath('app/' . $this->folder);

        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $src = $this->formatFile($this->folder, $file->getPathname());

                    $this->app->bind("panel.$src->handle", function () use ($src) {
                        return new $src->class();
                    });

                    $this->panels->push("panel.$src->handle");
                }
            }
        }
    }

    public function enqueueBlockEditorAssets(): void
    {
        $panels = $this->panels;

        if (empty($panels)) {
            return;
        }

        $gutengood_panels = [];

        foreach ($panels as $panel) {
            $gutengood_panels[] = (object) [
                'name' => "{$this->app[$panel]->name}",
                'icon' => $this->getIcon($this->app[$panel]->icon),
                'title' => $this->app[$panel]->title,
                'post_types' => $this->app[$panel]->post_types,
            ];
        }

        if (empty($gutengood_panels)) {
            return;
        }

        wp_localize_script('editor', 'gutengoodPanels', $gutengood_panels);
    }

    public function panelEndpoint(): void
    {
        $panels = $this->panels;

        if (empty($panels)) {
            return;
        }

        foreach ($panels as $panel) {
            $this->app[$panel]->panelEndpoint();
        }
    }

    /**
     * Register post meta for using in panels (optional)
     *
     * @return void
     */
    public function registerMeta(): void
    {
        $panels = $this->panels;

        if (empty($panels)) {
            return;
        }

        foreach ($panels as $panel) {
            array_map(function (array $meta): void {
                if (empty($meta) || empty($meta['meta_key'])) {
                    return;
                }

                register_post_meta(
                    '',
                    $meta['meta_key'],
                    [
                        'show_in_rest' => $meta['show_in_rest'] ?? true,
                        'single' => true,
                        'type' => $meta['type'],
                        'default' => $meta['default'],
                        'auth_callback' => fn(): bool => current_user_can('edit_posts'),
                    ]
                );
            }, $this->app[$panel]->panelMeta());
        }
    }
}
