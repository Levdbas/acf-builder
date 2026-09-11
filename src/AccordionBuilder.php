<?php

namespace StoutLogic\AcfBuilder;

/**
 * Builds configurations for an ACF accordion field.
 * @api
 */
class AccordionBuilder extends TabBuilder
{
    /**
     * @api
     */
    public function __construct($name, $type = 'accordion', $config = [])
    {
        parent::__construct($name, $type, $config);
    }

    /**

     * @api

     */

    public function setOpen($value = 1)
    {
        return $this->setConfig('open', $value ? 1 : 0);
    }

    /**

     * @api

     */

    public function setMultiExpand($value = 1)
    {
        return $this->setConfig('multi_expand', $value ? 1 : 0);
    }
}
