<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Editor;

use Yarovikov\Gutengood\Traits\Helpers;

class AbstractPanel
{
    use Helpers;

    /**
     * Panel title
     *
     * @var string
     */
    public string $title = '';

    /**
     * Panel icon
     *
     * @var string
     */
    public string $icon = '';

    /**
     * Panel mame
     *
     * @var string
     */
    public string $name = '';

    public array $post_types = [];

    public function panelEndpoint(): void
    {
        register_rest_route("{$this->name}/v1", '/data', [
            'methods' => 'GET',
            'callback' => fn(): array => $this->options(),
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Options
     *
     * @return array
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Meta
     *
     * @return array
     */
    public function panelMeta(): array
    {
        $components = [
            ...!empty($this->options()[0]['fields']) ? $this->options()[0]['fields'] : [],
        ];

        return array_filter(array_map(function (array $component): ?array {
            if (false === ($component['meta'] ?? false)) {
                return null;
            }

            return $this->buildMetaArgs($component);
        }, $components));
    }
}
