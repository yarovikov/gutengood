import {
  TimePicker,
  TextControl,
  TextareaControl,
  ToggleControl,
  SelectControl,
  ColorPalette,
  ColorPicker,
  BaseControl,
  RangeControl,
} from '@wordpress/components';
import {
  MediaUpload,
  MediaUploadCheck,
  RichText,
  __experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import {useState, useEffect} from '@wordpress/element';
import {ImagePreview} from "./image-preview";
import {File} from "./file";
import {SortableList} from "./sortable-list";

export default function BlockComponents({attributes, components, onChange, props, item = null, id = null}) {

  const [componentStates, setComponentStates] = useState({});

  // component condition
  useEffect(() => {
    const initialStates = {};
    components.forEach(component => {
      initialStates[component.name] = item ? item[component.name] : attributes[component.name];
    });
    setComponentStates(initialStates);
  }, [components, item, attributes]);

  const handleChange = (id, name, value) => {
    setComponentStates((prevState) => ({
      ...prevState,
      [name]: value,
    }));
    onChange(id, name, value);
  };

  const shouldRenderComponent = (component) => {
    if (component.condition) {
      const condition = Array.isArray(component.condition) ? component.condition : [component.condition];
      return condition.every(con =>
        componentStates[con.name] === con.value
      );
    }
    return true;
  };

  const renderBlockComponents = (component) => {
    switch (component.type) {
      case 'TimePicker':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <TimePicker
              key={component.name}
              currentDate={item ? item[component.name] : attributes[component.name]}
              onChange={(value) => onChange(id, component.name, value)}
              {...(component.is12hour ? {is12Hour: true} : {})}
            />
          </BaseControl>
        );
      case 'Text':
        return (
          <TextControl
            key={component.name}
            label={component.label}
            help={component.help}
            value={item ? item[component.name] : attributes[component.name]}
            onChange={(value) => onChange(id, component.name, value)}
          />
        );
      case 'Textarea':
        return (
          <TextareaControl
            key={component.name}
            label={component.label}
            help={component.help}
            value={item ? item[component.name] : attributes[component.name]}
            onChange={(value) => onChange(id, component.name, value)}
          />
        );
      case 'Toggle':
        return (
          <ToggleControl
            key={component.name}
            label={component.label}
            help={component.help}
            checked={item ? item[component.name] : attributes[component.name]}
            onChange={(value) => handleChange(id, component.name, value)}
          />
        );
      case 'Select':
        return (
          <SelectControl
            key={component.name}
            label={component.label}
            help={component.help}
            value={item ? item[component.name] : attributes[component.name]}
            onChange={(value) => handleChange(id, component.name, value)}
            options={[
              ...component.choices,
            ]}
          />
        );
      case 'ColorPalette':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <ColorPalette
              value={item ? item[component.name] : attributes[component.name]}
              onChange={(value) => onChange(id, component.name, value)}
              colors={[
                ...component.colors,
              ]}
              disableCustomColors={true}
              clearable={false}
            />
          </BaseControl>
        );
      case 'ColorPicker':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <ColorPicker
              key={component.name}
              color={item ? item[component.name] : attributes[component.name]}
              defaultValue={item ? item[component.name] : attributes[component.name]}
              onChange={(value) => handleChange(id, component.name, value)}
              {...(component.alfa ? {enableAlpha: true} : {})}
            />
          </BaseControl>
        );
      case 'Image':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <MediaUploadCheck>
              <MediaUpload
                onSelect={(media) => onChange(id, component.name, media.id)}
                allowedTypes={['image']}
                value={item ? item[component.name] : attributes[component.name]}
                render={({open}) =>
                  <ImagePreview
                    open={open}
                    remove={() => onChange(id, component.name, 0)}
                    componentName={component.name}
                    mediaId={item ? item[component.name] : attributes[component.name]}
                  />
                }
              />
            </MediaUploadCheck>
          </BaseControl>
        );
      case 'File':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <MediaUploadCheck>
              <MediaUpload
                onSelect={(file) => {
                  onChange(id, component.name, {
                    id: file.id ?? '',
                    name: file.filename ?? '',
                    url: file.url ?? '',
                    size: file.filesizeHumanReadable ?? '',
                  })
                }}
                value={item ? item[component.name] : attributes[component.name]}
                render={({open}) =>
                  <File
                    open={open}
                    remove={() => onChange(id, component.name, {})}
                    componentName={component.name}
                    file={item ? item[component.name] : attributes[component.name]}
                  />
                }
              />
            </MediaUploadCheck>
          </BaseControl>
        );
      case 'Link':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <LinkControl
              searchInputPlaceholder="Search..."
              value={item ? item[component.name] : attributes[component.name]}
              onChange={(value) => onChange(id, component.name, value)}
              hasTextControl
              onRemove={() => onChange(id, component.name, {})}
            />
          </BaseControl>
        );
      case 'Range':
        return (
          <RangeControl
            key={component.name}
            label={component.label}
            help={component.help}
            value={item ? item[component.name] : attributes[component.name]}
            onChange={(value) => onChange(id, component.name, value)}
            min={component.min ?? 300}
            max={component.max ?? 1536}
            step={component.step ?? 10}
          />
        );
      case 'RichText':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <RichText
              key={component.name}
              label={component.label}
              value={item ? item[component.name] : attributes[component.name]}
              onChange={(value) => onChange(id, component.name, value)}
              placeholder={component.placeholder ?? '...'}
            />
          </BaseControl>
        );
      case 'Message':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          />
        );
      case 'Repeater':
        return (
          <BaseControl
            key={component.name}
            label={component.label}
            help={component.help}
          >
            <SortableList
              key={component.name}
              componentName={component.name}
              fields={component.fields}
              props={{...props, buttonLabel: component.button_label || 'Add'}}
            />
          </BaseControl>
        );
      default:
        return null;
    }
  };

  return (
    <>
      {components.map((component) => {
        return shouldRenderComponent(component) ? renderBlockComponents(component) : null;
      })}
    </>
  )
}
