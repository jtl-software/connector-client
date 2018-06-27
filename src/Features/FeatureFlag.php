<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2018 JTL-Software GmbH
 */
namespace jtl\Connector\Client\Features;

use JMS\Serializer\Annotation as Serializer;

class FeatureFlag
{
    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("name")
     * @Serializer\Accessor(getter="getName",setter="setName")
     */
    protected $name = '';

    /**
     * @var boolean
     * @Serializer\Type("boolean")
     * @Serializer\SerializedName("active")
     * @Serializer\Accessor(getter="isActive",setter="setActive")
     */
    protected $active = false;

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
     * @param string $name
     * @return FeatureFlag
     */
    public function setName(string $name): FeatureFlag
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     * @return FeatureFlag
     */
    public function setActive(bool $active): FeatureFlag
    {
        $this->active = $active;
        return $this;
    }
}