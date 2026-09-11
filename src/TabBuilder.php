<?php

namespace StoutLogic\AcfBuilder;

/**
 * Builds configurations for an ACF Field
 * @api
 */
class TabBuilder extends FieldBuilder
{
    /**
     * @param string $name Field Name, conventionally 'snake_case'.
     * @param string $type Field Type.
     * @param array $config Additional Field Configuration.
     * @api
     */
    public function __construct($name, $type = 'tab', $config = [])
    {
        $config = array_merge([
            'label' => $this->generateLabel($name),
        ], $config);
        $name = $this->generateName($name) . '_' . $type;

        parent::__construct($name, $type, $config);
    }

    /**
     * Mark this tab as the endpoint of a tab group.
     *
     * @example
     * ```php
     * $fields
     *  ->addTab('Settings')
     *  ->endpoint();
     * ```
     * @api
     */
    public function endpoint()
    {
        return $this->setConfig('endpoint', 1);
    }

    /**

     * @api

     */

    public function removeEndpoint()
    {
        return $this->setConfig('endpoint', 0);
    }
}
