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
	 * Js remote dependencies
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
			'attributes' => [
				...$this->attributes,
				...$this->defaultAttributes(),
			],
			'render_callback' => fn(array $attributes, string $content): View => view($this->view, $this->getBlockData($attributes, $content)),
		]);
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

		$blocks = parse_blocks($post->post_content);
		if (empty($blocks)) {
			return;
		}

		foreach ($blocks as $block) {
			if ($this->name === $block['blockName']) {
				array_map(function (array $asset) use ($block): void {
					if (empty($asset['condition']) || (is_callable($asset['condition']) && $asset['condition']($block))) {
						bundle($asset['handle'])->enqueue();
					}
				}, $assets);
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
			'data' => $this->data(),
		];
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
				'type' => 'TextControl',
				'label' => 'Margin Top Desktop',
				'value' => '',
			],
			[
				'name' => 'margin_top_mobile',
				'type' => 'TextControl',
				'label' => 'Margin Top Mobile',
				'value' => '',
			],
			[
				'name' => 'margin_bottom_desktop',
				'type' => 'TextControl',
				'label' => 'Margin Bottom Desktop',
				'value' => '',
			],
			[
				'name' => 'margin_bottom_mobile',
				'type' => 'TextControl',
				'label' => 'Margin Bottom Mobile',
				'value' => '',
			],
		];
	}

	/**
	 * Default attributes (margins). Used for block_styles
	 *
	 * @return array
	 */
	public function defaultAttributes(): array
	{
		return [
			'margin_top_desktop' => [
				'type' => 'string',
				'default' => '0',
			],
			'margin_top_mobile' => [
				'type' => 'string',
				'default' => '0',
			],
			'margin_bottom_desktop' => [
				'type' => 'string',
				'default' => '0',
			],
			'margin_bottom_mobile' => [
				'type' => 'string',
				'default' => '0',
			],
		];
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
	 * Default inline block styles.
	 *
	 * Check block blade view. Use in your app.css like this
	 * .gutengood-block {
	 *   margin-top: var(--block-margin-top-mobile);
	 *   margin-bottom: var(--block-margin-bottom-mobile);
	 *   @media screen(lg) {
	 *     margin-top: var(--block-margin-top-desktop);
	 *     margin-bottom: var(--block-margin-bottom-desktop);
	 *   }
	 * }
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
