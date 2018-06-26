<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2018 JTL-Software GmbH
 */
namespace jtl\Connector\Client\Features;

class FeaturesCollection
{
    /**
     * @var FeatureEntity[]
     */
    protected $entities = [];

    /**
     * @var FeatureFlag[]
     */
    protected $flags = [];

    /**
     * Features constructor.
     * @param FeatureEntity[] $entities
     * @param FeatureFlag[] $flags
     */
    public function __construct(array $entities = [], array $flags = [])
    {
        $this->setEntities(...$entities);
        $this->setFlags(...$flags);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function hasEntity(string $name): bool
    {
        return isset($this->entities[$name]);
    }

    /**
     * @param string $name
     * @return FeatureEntity
     */
    public function getEntity(string $name): FeatureEntity
    {
        if($this->hasEntity($name)) {
            return $this->entities[$name];
        }
        throw new \RuntimeException('An entity with name ' . $name . 'does not exist!');
    }

    /**
     * @param FeatureEntity ...$entities
     * @return FeaturesCollection
     */
    public function setEntities(FeatureEntity ...$entities): FeaturesCollection
    {
        foreach($entities as $entity) {
            $this->setEntity($entity);
        }
        return $this;
    }

    /**
     * @param FeatureEntity $entity
     * @return FeaturesCollection
     */
    public function setEntity(FeatureEntity $entity): FeaturesCollection
    {
        $this->entities[$entity->getName()] = $entity;
        return $this;
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function hasFlag(string $name): bool
    {
        return isset($this->flags[$name]);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function isFlagActive(string $name): bool
    {
        if($this->hasFlag($name)) {
            return $this->flags[$name]->isActive();
        }
        throw new \RuntimeException('A flag with the name ' . $name . ' does not exist!');
    }

    /**
     * @param FeatureFlag ...$flags
     * @return FeaturesCollection
     */
    public function setFlags(FeatureFlag ...$flags): FeaturesCollection
    {
        foreach($flags as $flag) {
            $this->setFlag($flag);
        }
        return $this;
    }

    /**
     * @param FeatureFlag $flag
     * @return FeaturesCollection
     */
    public function setFlag(FeatureFlag $flag): FeaturesCollection
    {
        $this->flags[$flag->getName()] = $flag;
        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = [
            'entities' => [],
            'flags' => [],
        ];

        foreach($this->entities as $entity) {
            $data['entities'][$entity->getName()] = $entity->toArray();
        }

        foreach ($this->flags as $flag) {
            $data['flags'][$flag->getName()] = $flag->isActive();
        }

        return $data;
    }

    /**
     * @param FeatureEntity[] $entities
     * @param FeatureFlag[] $flags
     * @return FeaturesCollection
     */
    public static function create(array $entities = [], array $flags = []): FeaturesCollection
    {
        $featureEntities = [];
        foreach ($entities as $name => $methods) {
            $entity = new FeatureEntity($name);
            foreach ($methods as $methodName => $value) {
                $setter = 'set' . ucfirst($methodName);
                if (method_exists($entity, $setter)) {
                    $entity->$setter($value);
                }
            }
            $featureEntities[] = $entity;
        }

        $featureFlags = [];
        foreach ($flags as $name => $value) {
            $featureFlags[] = new FeatureFlag($name, $value);
        }
        return new static($featureEntities, $featureFlags);
    }
}