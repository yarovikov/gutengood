<?php

declare(strict_types=1);

namespace Yarovikov\Gutengood\Editor;

class GutengoodBuilder
{
    protected array $components = [];
    protected array $repeater = [];
    protected array $section = [];

    protected function addComponent(string $name, string $type, array $args = []): self
    {
        $component = [
            'name' => $name,
            'type' => $type,
            ...$args,
        ];

        if (!empty($this->section)) {
            if (!empty($this->repeater)) {
                $this->repeater['fields'][] = $component;
            } else {
                $this->section['fields'][] = $component;
            }
        } else {
            $this->section = [
                'name' => __('Block Options'),
                'type' => 'Section',
                'open' => true,
            ];
            if (!empty($this->repeater)) {
                $this->repeater['fields'][] = $component;
            } else {
                $this->section['fields'][] = $component;
            }
        }

        return $this;
    }

    /**
     * Text control component
     *
     * @param string $name The name of the component.
     * @param array $args (string label, string help, string value)
     *
     * @return self
     */
    public function addText(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'Text', $args);
    }

    /**
     * Textarea control component
     *
     * @param string $name The name of the component.
     * @param array $args (string label, string help, string value)
     *
     * @return self
     */
    public function addTextarea(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'Textarea', $args);
    }

    /**
     * Toggle control component
     *
     * @param string $name The name of the component.
     * @param array $args (string label, bool value)
     *
     * @return self
     */
    public function addToggle(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'Toggle', $args);
    }

    /**
     * ColorPalette control component
     *
     * @param string $name The name of the component.
     * @param array $args (string label, array colors (string name, string color hex, string slug), string value)
     *
     * @return self
     */
    public function addColorPalette(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'ColorPalette', $args);
    }

    /**
     * Select control component
     *
     * @param string $name The name of the component.
     * @param array $args (string label, array choices (string label, string value), string value)
     *
     * @return self
     */
    public function addSelect(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'Select', $args);
    }

    /**
     * Image component
     *
     * @param string $name The name of the component.
     * @param array $args (string label, int value)
     *
     * @return self
     */
    public function addImage(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'Image', $args);
    }

    /**
     * RichText component
     *
     * @param string $name The name of the component.
     * @param array $args (string placeholder, string value)
     *
     * @return self
     */
    public function addRichText(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'RichText', $args);
    }

    /**
     * Range control component
     *
     * @param string $name The name of the component.
     * @param array $args (string placeholder, int step, int min, int max, int value)
     *
     * @return self
     */
    public function addRange(string $name, array $args = []): self
    {
        return $this->addComponent($name, 'Range', $args);
    }

    /**
     * Repeater
     *
     * @param string $name The name of the repeater component.
     *
     * @return self
     */
    public function addRepeater(string $name): self
    {
        $this->repeater = [
            'name' => $name,
            'type' => 'Repeater',
            'fields' => [],
        ];

        return $this;
    }

    public function endRepeater(): self
    {
        $this->section['fields'][] = $this->repeater;
        $this->repeater = [];

        return $this;
    }

    /**
     * Section. Use this as accordion panel for options
     *
     * @param string $name The name of the section.
     * @param array $args (bool open)
     *
     * @return self
     */
    public function addSection(string $name, array $args = []): self
    {
        if (!empty($this->section)) {
            $this->components[] = $this->section;
        }

        $this->section = [
            'name' => $name,
            'type' => 'Section',
            'fields' => [],
            ...$args,
        ];

        return $this;
    }

    public function endSection(): self
    {
        if (!empty($this->section)) {
            $this->components[] = $this->section;
            $this->section = [];
        }

        return $this;
    }

    public function conditional(string $name, mixed $value): self
    {
        if (!empty($this->section)) {
            if (!empty($this->repeater)) {
                $index = count($this->repeater['fields']) - 1;
                if ($index >= 0) {
                    $this->repeater['fields'][$index]['condition'] = ['name' => $name, 'value' => $value];
                }
            } else {
                $index = count($this->section['fields']) - 1;
                if ($index >= 0) {
                    $this->section['fields'][$index]['condition'] = ['name' => $name, 'value' => $value];
                }
            }
        }

        return $this;
    }

    public function build(): array
    {
        if (!empty($this->section)) {
            $this->components[] = $this->section;
        }

        return $this->components;
    }
}
