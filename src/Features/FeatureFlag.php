<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2018 JTL-Software GmbH
 */
namespace jtl\Connector\Client\Features;


class FeatureFlag
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var boolean
     */
    protected $active;

    /**
     * FeatureFlag constructor.
     * @param string $name
     * @param boolean $active
     */
    public function __construct(string $name, bool $active = false)
    {
        $this->name = $name;
        $this->active = $active;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}