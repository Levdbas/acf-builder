<?php

namespace StoutLogic\AcfBuilder;

use StoutLogic\AcfBuilder\Exceptions\FieldNameCollisionException;
use StoutLogic\AcfBuilder\Exceptions\FieldNotFoundException;
use StoutLogic\AcfBuilder\Exceptions\ModifyFieldReturnTypeException;

/**
 * Builds configurations for ACF Field Groups
 * @api
 */
class FieldsBuilder extends ParentDelegationBuilder implements NamedBuilder
{
    /**
     * Field Group Configuration
     * @var array
     */
    protected $config = [];

    /**
     * Manages the Field Configurations
     * @var FieldManager
     */
    protected $fieldManager;

    /**
     * Location configuration for Field Group
     * @var LocationBuilder
     */
    protected $location;

    /**
     * Field Group Name
     * @var string
     */
    protected $name;

    const DEEP_NESTING_DELIMITER = '->';

    /**
     * @param string $name Field Group name
     * @param array $groupConfig Field Group configuration
     * @api
     */
    public function __construct($name, array $groupConfig = [])
    {
        $this->fieldManager = new FieldManager();
        $this->name = $name;
        $this->setGroupConfig('key', $name);
        $this->setGroupConfig('title', $this->generateLabel($name));

        $this->config = array_merge($this->config, $groupConfig);
    }

    /**
     * Set a value for a particular key in the group config
     * @param string $key
     * @param mixed $value
     * @return $this
     * @api
     */
    public function setGroupConfig($key, $value)
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * Get a value for a particular key in the group config.
     * Returns null if the key isn't defined in the config.
     * @param string $key
     * @return mixed|null
     * @api
     */
    public function getGroupConfig($key)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return null;
    }

    /**

     * @api

     */

    public function updateGroupConfig($config)
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * @return string
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Namespace a group key
     * Append the namespace 'group' before the set key.
     *
     * @param  string $key Field Key
     * @return string      Field Key
     */
    private function namespaceGroupKey($key)
    {
        if (strpos($key, 'group_') !== 0) {
            $key = 'group_' . $key;
        }
        return $key;
    }

    /**
     * Build the final config array. Build any other builders that may exist
     * in the config.
     * @return array Final field config
     * @example
     *
     * ```php
     * $config = $fields->build();
     * ```
     * @api
     */
    public function build()
    {
        return array_merge($this->config, [
            'fields' => $this->buildFields(),
            'location' => $this->buildLocation(),
            'key' => $this->namespaceGroupKey($this->config['key']),
        ]);
    }

    /**
     * Return a fields config array
     * @return array
     */
    private function buildFields()
    {
        $fields = array_map(function ($field) {
            return ($field instanceof Builder) ? $field->build() : $field;
        }, $this->getFields());

        return $this->transformFields($fields);
    }

    /**
     * Apply field transforms
     * @param  array $fields
     * @return array Transformed fields config
     */
    private function transformFields($fields)
    {
        $conditionalTransform = new Transform\ConditionalLogic($this);
        $namespaceFieldKeyTransform = new Transform\NamespaceFieldKey($this);

        return
            $namespaceFieldKeyTransform->transform(
                $conditionalTransform->transform($fields)
            );
    }

    /**
     * Return a locations config array
     * @return array|LocationBuilder
     */
    private function buildLocation()
    {
        $location = $this->getLocation();
        return ($location instanceof Builder) ? $location->build() : $location;
    }

    /**
     * Add multiple fields either via an array or from another builder
     * @param FieldsBuilder|array $fields
     * @return $this
     * @example
     *
     * ```php
     *
     * $backgroundSettings = new FieldsBuilder('background_settings');
     *
     * $backgroundSettings
     *  ->addColorPicker('background_color')
     *  ->addColorPicker('text_color');
     *
     * // reuse the existing background settings
     * $fields->addFields($backgroundSettings);
     * ```
     * @api
     */
    public function addFields($fields)
    {
        if ($fields instanceof FieldsBuilder) {
            $builder = clone $fields;
            $fields = $builder->getFields();
        }

        foreach ($fields as $field) {
            $this->getFieldManager()->pushField($field);
        }

        return $this;
    }

    /**
     * Add a field of a specific type
     *
     * You can use this to add custom field types that are not predefined.
     *
     * @param string $name
     * @param string $type
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     *
     * @example
     * ```php
     * $fields->addField('rating', 'star_rating');
     * ```
     * @api
     */
    public function addField($name, $type, array $args = [])
    {
        return $this->initializeField(new FieldBuilder($name, $type, $args));
    }

    /**
     * Add a field of a choice type, allows choices to be added.
     *
     * @param string $name
     * @param string $type  can be `select`, `radio`, `checkbox`
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addChoiceField('color', 'select', ['choices' => ['red', 'green', 'blue']]);
     * ```
     * @api
     */
    public function addChoiceField($name, $type, array $args = [])
    {
        return $this->initializeField(new ChoiceFieldBuilder($name, $type, $args));
    }

    /**
     * Initialize the FieldBuilder, add to FieldManager
     *
     * @param  FieldBuilder $field
     * @return FieldBuilder
     */
    protected function initializeField($field)
    {
        $field->setParentContext($this);
        $this->getFieldManager()->pushField($field);
        return $field;
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addText('title', [
     *     'default_value'             => '',
     *     'placeholder'               => 'Enter a title',
     *     'maxlength'                 => 80,
     *     'prepend'                   => '',
     *     'append'                    => '',
     * ]);
     * ```
     * @api
     */
    public function addText($name, array $args = [])
    {
        return $this->addField($name, 'text', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addTextarea('summary', [
     *     'default_value'             => '',
     *     'rows'                      => 4,
     *     'maxlength'                 => 500,
     *     'placeholder'               => 'Write a summary',
     *     'new_lines'                 => 'wpautop',
     * ]);
     * ```
     * @api
     */
    public function addTextarea($name, array $args = [])
    {
        return $this->addField($name, 'textarea', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addNumber('price', [
     *     'default_value'             => 0,
     *     'prepend'                   => '$',
     *     'min'                       => 0,
     *     'max'                       => 100000,
     *     'step'                      => 0.01,
     * ]);
     * ```
     * @api
     */
    public function addNumber($name, array $args = [])
    {
        return $this->addField($name, 'number', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addEmail('email', [
     *     'default_value'             => '',
     *     'placeholder'               => 'name@example.com',
     *     'prepend'                   => '',
     *     'append'                    => '',
     *     'required'                  => 1,
     * ]);
     * ```
     * @api
     */
    public function addEmail($name, array $args = [])
    {
        return $this->addField($name, 'email', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addUrl('website', [
     *     'default_value'             => '',
     *     'placeholder'               => 'https://example.com',
     * ]);
     * ```
     * @api
     */
    public function addUrl($name, array $args = [])
    {
        return $this->addField($name, 'url', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addPassword('password', [
     *     'placeholder'               => 'Enter a password',
     *     'prepend'                   => '',
     *     'append'                    => '',
     * ]);
     * ```
     * @api
     */
    public function addPassword($name, array $args = [])
    {
        return $this->addField($name, 'password', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addWysiwyg('content', [
     *     'default_value'             => '',
     *     'tabs'                      => 'all',
     *     'toolbar'                   => 'basic',
     *     'media_upload'              => 0,
     *     'delay'                     => 0,
     * ]);
     * ```
     * @api
     */
    public function addWysiwyg($name, array $args = [])
    {
        return $this->addField($name, 'wysiwyg', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addOembed('video', ['width' => 800, 'height' => 450]);
     * ```
     * @api
     */
    public function addOembed($name, array $args = [])
    {
        return $this->addField($name, 'oembed', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addImage('image', [
     *     'return_format'             => 'array',
     *     'preview_size'              => 'medium',
     *     'library'                   => 'all',
     *     'min_width'                 => 0,
     *     'min_height'                => 0,
     *     'min_size'                  => 0,
     *     'max_width'                 => 0,
     *     'max_height'                => 0,
     *     'max_size'                  => 0,
     *     'mime_types'                => '',
     * ]);
     * ```
     * @api
     */
    public function addImage($name, array $args = [])
    {
        return $this->addField($name, 'image', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addFile('download', [
     *     'return_format'             => 'array',
     *     'library'                   => 'all',
     *     'min_size'                  => 0,
     *     'max_size'                  => 0,
     *     'mime_types'                => 'pdf,doc,docx',
     * ]);
     * ```
     * @api
     */
    public function addFile($name, array $args = [])
    {
        return $this->addField($name, 'file', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addGallery('gallery', [
     *     'return_format'             => 'array',
     *     'library'                   => 'all',
     *     'min'                       => 1,
     *     'max'                       => 10,
     *     'min_width'                 => 0,
     *     'min_height'                => 0,
     *     'min_size'                  => 0,
     *     'max_width'                 => 0,
     *     'max_height'                => 0,
     *     'max_size'                  => 0,
     *     'mime_types'                => '',
     *     'insert'                    => 'append',
     *     'preview_size'              => 'medium',
     * ]);
     * ```
     * @api
     */
    public function addGallery($name, array $args = [])
    {
        return $this->addField($name, 'gallery', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addTrueFalse('featured', [
     *     'message'                   => 'Feature this item',
     *     'default_value'             => 0,
     *     'ui'                        => 1,
     *     'ui_on_text'                => 'Yes',
     *     'ui_off_text'               => 'No',
     * ]);
     * ```
     * @api
     */
    public function addTrueFalse($name, array $args = [])
    {
        return $this->addField($name, 'true_false', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addSelect('color', [
     *     'choices'                   => ['red' => 'Red', 'blue' => 'Blue'],
     *     'default_value'             => [],
     *     'return_format'             => 'value',
     *     'multiple'                  => 0,
     *     'allow_null'                => 0,
     *     'ui'                        => 1,
     *     'ajax'                      => 0,
     *     'create_options'            => 0,
     *     'save_options'              => 0,
     * ]);
     * ```
     * @api
     */
    public function addSelect($name, array $args = [])
    {
        return $this->addChoiceField($name, 'select', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addRadio('color', [
     *     'choices'                   => ['red' => 'Red', 'blue' => 'Blue'],
     *     'default_value'             => '',
     *     'return_format'             => 'value',
     *     'allow_null'                => 0,
     *     'other_choice'              => 0,
     *     'save_other_choice'         => 0,
     *     'layout'                    => 'horizontal',
     * ]);
     * ```
     * @api
     */
    public function addRadio($name, array $args = [])
    {
        return $this->addChoiceField($name, 'radio', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addCheckbox('features', [
     *     'choices'                   => ['audio', 'video'],
     *     'default_value'             => [],
     *     'return_format'             => 'value',
     *     'allow_custom'              => 0,
     *     'save_custom'               => 0,
     *     'layout'                    => 'horizontal',
     *     'toggle'                    => 0,
     * ]);
     * ```
     * @api
     */
    public function addCheckbox($name, array $args = [])
    {
        return $this->addChoiceField($name, 'checkbox', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addButtonGroup('alignment', [
     *     'choices'                   => ['left' => 'Left', 'center' => 'Center'],
     *     'default_value'             => '',
     *     'return_format'             => 'value',
     *     'allow_null'                => 0,
     *     'layout'                    => 'horizontal',
     * ]);
     * ```
     * @api
     */
    public function addButtonGroup($name, array $args = [])
    {
        return $this->addChoiceField($name, 'button_group', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addPostObject('related_post', [
     *     'post_type'                 => ['post'],
     *     'post_status'               => ['publish'],
     *     'taxonomy'                  => [],
     *     'return_format'             => 'object',
     *     'multiple'                  => 0,
     *     'allow_null'                => 0,
     * ]);
     * ```
     * @api
     */
    public function addPostObject($name, array $args = [])
    {
        return $this->addField($name, 'post_object', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addPageLink('related_page', [
     *     'post_type'                 => ['page'],
     *     'post_status'               => ['publish'],
     *     'taxonomy'                  => [],
     *     'allow_archives'            => 1,
     *     'multiple'                  => 0,
     *     'allow_null'                => 0,
     * ]);
     * ```
     * @api
     */
    public function addPageLink($name, array $args = [])
    {
        return $this->addField($name, 'page_link', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addRelationship('related_content', [
     *     'post_type'                 => ['post'],
     *     'post_status'               => ['publish'],
     *     'taxonomy'                  => [],
     *     'filters'                   => ['search', 'post_type'],
     *     'return_format'             => 'object',
     *     'min'                       => 0,
     *     'max'                       => 0,
     *     'elements'                  => ['featured_image'],
     * ]);
     * ```
     * @api
     */
    public function addRelationship($name, array $args = [])
    {
        return $this->addField($name, 'relationship', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addTaxonomy('topics', [
     *     'taxonomy'                  => 'category',
     *     'add_term'                  => 1,
     *     'save_terms'                => 0,
     *     'load_terms'                => 0,
     *     'field_type'                => 'checkbox',
     *     'return_format'             => 'id',
     *     'allow_null'                => 0,
     * ]);
     * ```
     * @api
     */
    public function addTaxonomy($name, array $args = [])
    {
        return $this->addField($name, 'taxonomy', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addUser('editor', [
     *     'role'                      => ['editor'],
     *     'return_format'             => 'array',
     *     'multiple'                  => 0,
     *     'allow_null'                => 0,
     * ]);
     * ```
     * @api
     */
    public function addUser($name, array $args = [])
    {
        return $this->addField($name, 'user', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addDatePicker('published_on', [
     *     'display_format'            => 'd/m/Y',
     *     'save_format'               => 'Y-m-d',
     *     'return_format'             => 'Y-m-d',
     *     'first_day'                 => 1,
     *     'default_to_current_date'   => 0,
     * ]);
     * ```
     * @api
     */
    public function addDatePicker($name, array $args = [])
    {
        return $this->addField($name, 'date_picker', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addTimePicker('published_at', [
     *     'display_format'            => 'g:i a',
     *     'return_format'             => 'H:i:s',
     * ]);
     * ```
     * @api
     */
    public function addTimePicker($name, array $args = [])
    {
        return $this->addField($name, 'time_picker', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addDateTimePicker('published', [
     *     'display_format'            => 'd/m/Y g:i a',
     *     'return_format'             => 'Y-m-d H:i:s',
     *     'first_day'                 => 1,
     *     'default_to_current_date'   => 0,
     * ]);
     * ```
     * @api
     */
    public function addDateTimePicker($name, array $args = [])
    {
        return $this->addField($name, 'date_time_picker', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addColorPicker('brand_color', ['default_value' => '#2271b1']);
     *
     * Additional color picker settings include `enable_opacity`, `return_format`,
     * `show_custom_palette`, `custom_palette_source`, `palette_colors`, and
     * `show_color_wheel`.
     * ```
     * @api
     */
    public function addColorPicker($name, array $args = [])
    {
        return $this->addField($name, 'color_picker', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addGoogleMap('office_location', [
     *     'center_lat'                => '',
     *     'center_lng'                => '',
     *     'zoom'                      => 14,
     *     'height'                    => 400,
     * ]);
     * ```
     * @api
     */
    public function addGoogleMap($name, array $args = [])
    {
        return $this->addField($name, 'google_map', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addLink('cta_link', ['return_format' => 'array']);
     * ```
     * @api
     */
    public function addLink($name, array $args = [])
    {
        return $this->addField($name, 'link', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addRange('opacity', ['min' => 0, 'max' => 100, 'step' => 1]);
     *
     * The range field also supports `default_value`, `prepend`, and `append`.
     * ```
     * @api
     */
    public function addRange($name, array $args = [])
    {
        return $this->addField($name, 'range', $args);
    }

    /**
     * All fields added after will appear under this tab, until another tab
     * is added.
     * @param string $label Tab label
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addTab('Content', ['placement' => 'left']);
     *
     * Set `endpoint` to `1` to end the current tab group.
     * ```
     * @api
     */
    public function addTab($label, array $args = [])
    {
        return $this->initializeField(new TabBuilder($label, 'tab', $args));
    }

    /**
     * All fields added after will appear under this accordion, until
     * another accordion is added.
     * @param string $label Accordion label
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return AccordionBuilder
     * @example
     *
     * ```php
     * $fields->addAccordion('Advanced', ['open' => 1, 'multi_expand' => 1]);
     *
     * The accordion also supports the `endpoint` setting.
     * ```
     * @api
     */
    public function addAccordion($label, array $args = [])
    {
        return $this->initializeField(new AccordionBuilder($label, 'accordion', $args));
    }

    /**
     * Adds a message field
     *
     * @param string $label
     * @param string $message
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FieldBuilder
     * @example
     *
     * ```php
     * $fields->addMessage('Notice', 'Remember to save your changes.', [
     *     'new_lines'                 => 'wpautop',
     *     'esc_html'                  => 0,
     * ]);
     * ```
     * @api
     */
    public function addMessage($label, $message, array $args = [])
    {
        $name = $this->generateName($label) . '_message';
        $args = array_merge([
            'label' => $label,
            'message' => $message,
        ], $args);

        return $this->addField($name, 'message', $args);
    }

    /**
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return GroupBuilder
     * @example
     *
     * ```php
     * $fields->addGroup('author')->addText('name')->endGroup();
     *
     * A group can use the `layout` option with the values supported by ACF.
     * ```
     * @api
     */
    public function addGroup($name, array $args = [])
    {
        return $this->initializeField(new GroupBuilder($name, 'group', $args));
    }

    /**
     * Add a repeater field. Any fields added after will be added to the repeater
     * until `endRepeater` is called.
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return RepeaterBuilder
     * @example
     *
     * ```php
     * $fields->addRepeater('slides', [
     *     'layout'                    => 'block',
     *     'pagination'                => 0,
     *     'rows_per_page'             => 20,
     *     'min'                       => 1,
     *     'max'                       => 7,
     *     'button_label'              => 'Add Slide',
     *     'collapsed'                 => '',
     * ]);
     * ```
     * @api
     */
    public function addRepeater($name, array $args = [])
    {
        return $this->initializeField(new RepeaterBuilder($name, 'repeater', $args));
    }

    /**
     * Add a flexible content field. Once adding a layout with `addLayout`,
     * any fields added after will be added to that layout until another
     * `addLayout` call is made, or until `endFlexibleContent` is called.
     * @param string $name
     * @param array $args field configuration
     * @throws FieldNameCollisionException if name already exists.
     * @return FlexibleContentBuilder
     * @example
     *
     * ```php
     * $fields->addFlexibleContent('sections', [
     *     'min'                       => 0,
     *     'max'                       => 0,
     *     'button_label'              => 'Add Section',
     * ]);
     * ```
     * @api
     */
    public function addFlexibleContent($name, array $args = [])
    {
        return $this->initializeField(new FlexibleContentBuilder($name, 'flexible_content', $args));
    }

    /**
     * @return FieldManager
     */
    protected function getFieldManager()
    {
        return $this->fieldManager;
    }

    /**
     * @return FieldBuilder[]
     * @api
     */
    public function getFields()
    {
        return $this->getFieldManager()->getFields();
    }

    /**
     * Return int of fields
     * @return int field count
     */
    public function getCount()
    {
        return $this->getFieldManager()->getCount();
    }

    /**
     * @param string $name [description]
     * @return FieldBuilder
     * @api
     */
    public function getField($name)
    {
        return $this->getFieldManager()->getField($name);
    }

    /**

     * @api

     */

    public function fieldExists($name)
    {
        return $this->getFieldManager()->fieldNameExists($name);
    }

    /**
     * Modify an already defined field
     * @param  string $name   Name of the field
     * @param  array|\Closure  $modify Array of field configs or a closure that accepts
     * a FieldsBuilder and returns a FieldsBuilder.
     * @throws ModifyFieldReturnTypeException if $modify is a closure and doesn't
     * return a FieldsBuilder.
     * @throws FieldNotFoundException if the field name doesn't exist.
     * @return $this
     * @example
     *
     * ```php
     * $fields->modifyField('title', ['label' => 'Headline']);
     * ```
     * @api
     */
    public function modifyField($name, $modify)
    {
        if ($this->hasDeeplyNestedField($name)) {
            $fieldNames = explode(self::DEEP_NESTING_DELIMITER, $name, 2);
            $this->getField($fieldNames[0])->modifyField($fieldNames[1], $modify);

            return $this;
        }

        if (is_array($modify)) {
            $this->getFieldManager()->modifyField($name, $modify);
            return $this;
        } elseif ($modify instanceof \Closure) {
            $field = $this->getField($name);

            // Initialize Modifying FieldsBuilder
            $modifyBuilder = new FieldsBuilder('');
            $modifyBuilder->addFields([$field]);

            /**
             * @var FieldsBuilder
             */
            $modifyBuilder = $modify($modifyBuilder);

            // Check if a FieldsBuilder is returned
            if (!$modifyBuilder instanceof FieldsBuilder) {
                throw new ModifyFieldReturnTypeException(gettype($modifyBuilder));
            }

            // Insert field(s)
            $this->getFieldManager()->replaceField($name, $modifyBuilder->getFields());
        }

        return $this;
    }

    /**
     * Remove a field by name
     * @param  string $name Field to remove
     * @return $this
     * @example
     *
     * ```php
     * $fields->removeField('title');
     * ```
     * @api
     */
    public function removeField($name)
    {
        if ($this->hasDeeplyNestedField($name)) {
            $fieldNames = explode(self::DEEP_NESTING_DELIMITER, $name, 2);
            $this->getField($fieldNames[0])->removeField($fieldNames[1]);
            return $this;
        }

        $this->getFieldManager()->removeField($name);

        return $this;
    }

    /**
     * @param string $name Deeply nested field name
     * @return bool
     */
    private function hasDeeplyNestedField($name)
    {
        return strpos($name, static::DEEP_NESTING_DELIMITER) !== false;
    }

    /**
     * Set the location of the field group. See
     * https://github.com/StoutLogic/acf-builder/wiki/location and
     * https://www.advancedcustomfields.com/resources/custom-location-rules/
     * for more details.
     * @param string $param
     * @param string $operator
     * @param string $value
     * @return LocationBuilder
     * @example
     *
     * ```php
     * $fields->setLocation('post_type', '==', 'page');
     * ```
     * @api
     */
    public function setLocation($param, $operator, $value)
    {
        if ($this->getParentContext()) {
            return $this->getParentContext()->setLocation($param, $operator, $value);
        }

        $this->location = new LocationBuilder($param, $operator, $value);
        $this->location->setParentContext($this);

        return $this->location;
    }

    /**
     * @return LocationBuilder
     * @api
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Create a field label based on the field's name. Generates title case.
     * @param  string $name
     * @return string label
     */
    protected function generateLabel($name)
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Generates a snaked cased name.
     * @param  string $name
     * @return string
     */
    protected function generateName($name)
    {
        return strtolower(str_replace(' ', '_', $name));
    }

    /**

     * @api

     */

    public function __clone()
    {
        $this->fieldManager = clone $this->fieldManager;
    }
}
