<?php

namespace Actinoids\Modlr\RestOdm\StoreSerializer;

use Actinoids\Modlr\RestOdm\Models\Model;
use Actinoids\Modlr\RestOdm\Models\Collection;
use Actinoids\Modlr\RestOdm\StoreAdapter\JsonApiAdapter;
use Actinoids\Modlr\RestOdm\Metadata\AttributeMetadata;
use Actinoids\Modlr\RestOdm\Metadata\EntityMetadata;
use Actinoids\Modlr\RestOdm\Metadata\RelationshipMetadata;
use Actinoids\Modlr\RestOdm\DataTypes\TypeFactory;
use Actinoids\Modlr\RestOdm\Rest\RestPayload;
use Actinoids\Modlr\RestOdm\Struct;
use Actinoids\Modlr\RestOdm\Exception\RuntimeException;
use Actinoids\Modlr\RestOdm\Adapter\AdapterInterface;

// class JsonApiSerializer implements SerializerInterface
class JsonApiSerializer
{
    /**
     * The type factory.
     * Used for converting values to the API data type format.
     *
     * @var TypeFactory
     */
    private $typeFactory;

    /**
     * Denotes the current object depth of the serializer.
     *
     * @var int
     */
    private $depth = 0;

    /**
     * Denotes types that should be converted to serialized format.
     *
     * @var array
     */
    private $typesRequiringConversion = [
        'date'      => true,
        'integer'   => true,
    ];

    /**
     * Constructor.
     *
     * @param   TypeFactory     $typeFactory
     */
    public function __construct(TypeFactory $typeFactory)
    {
        $this->typeFactory = $typeFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function serialize(Model $model, JsonApiAdapter $adapter)
    {
        // var_dump(__METHOD__, $model);
        // die();
        // $primaryData = $resource->getPrimaryData();
        // $serialized['data'] = $this->serializeData($primaryData, $adapter);
        $serialized['data'] = $this->serializeModel($model, $adapter);

        // if (0 === $this->depth && $resource->hasIncludedData()) {
        //     $serialized['included'] = $this->serializeData($resource->getIncludedData(), $adapter);
        // }
        return (0 === $this->depth) ? new RestPayload($this->encode($serialized)) : $serialized;
    }

    /**
     * {@inheritDoc}
     */
    public function serializeCollection(Collection $collection, JsonApiAdapter $adapter)
    {
        $serialized['data'] = [];
        foreach ($collection as $model) {
            $serialized['data'][] = $this->serializeModel($model, $adapter);
        }
        return (0 === $this->depth) ? new RestPayload($this->encode($serialized)) : $serialized;
    }

    /**
     * {@inheritDoc}
     */
    public function serializeModel(Model $model, JsonApiAdapter $adapter)
    {
        // $metadata = $adapter->getEntityMetadata($entity->getType());

        $metadata = $model->getMetadata();
        $serialized = [
            'type'  => $model->getType(),
            'id'    => $model->getId(),
        ];
        if ($this->depth > 0) {
        //     // $this->includeResource($resource);
            return $serialized;
        }

        foreach ($metadata->getAttributes() as $key => $attrMeta) {
            $value = $model->get($key);
            $serialized['attributes'][$key] = $this->serializeAttribute($value, $attrMeta);
        }

        $serialized['links'] = ['self' => $adapter->buildUrl($metadata, $model->getId())];

        foreach ($metadata->getRelationships() as $key => $relMeta) {
            $relationship = $model->get($key);
            // $formattedKey = $adapter->getExternalFieldKey($key);
            $serialized['relationships'][$key] = $this->serializeRelationship($model, $relationship, $relMeta, $adapter);
        }
        return $serialized;
    }

    /**
     * Serializes a dataset into the appropriate format.
     *
     * @param   mixed               $data
     * @param   AdapterInterface    $adapter
     * @return  array
     * @throws  RuntimeException
     */
    // protected function serializeData($data, AdapterInterface $adapter)
    // {
    //     if ($data instanceof Struct\Entity) {
    //         $serialized = $this->serializeEntity($data, $adapter);
    //     } elseif ($data instanceof Struct\Identifier) {
    //         $serialized = $this->serializeIdentifier($data, $adapter);
    //     } elseif ($data instanceof Struct\Collection) {
    //         $serialized = $this->serializeCollection($data, $adapter);
    //     } elseif (null === $data) {
    //         $serialized = null;
    //     } else {
    //         throw new RuntimeException('Unable to serialize the provided data.');
    //     }
    //     return $serialized;
    // }

    /**
     * {@inheritDoc}
     */
    // public function serializeIdentifier(Struct\Identifier $identifier, AdapterInterface $adapter)
    // {
    //     $serialized = [
    //         'type'  => $adapter->getExternalEntityType($identifier->getType()),
    //         'id'    => $identifier->getId(),
    //     ];
    //     return $serialized;
    // }

    /**
     * {@inheritDoc}
     */
    // public function serializeEntity(Struct\Entity $entity, AdapterInterface $adapter)
    // {
    //     $metadata = $adapter->getEntityMetadata($entity->getType());

    //     $serialized = [
    //         'type'  => $adapter->getExternalEntityType($metadata->type),
    //         'id'    => $entity->getId(),
    //     ];
    //     if ($this->depth > 0) {
    //         // $this->includeResource($resource);
    //         return $serialized;
    //     }

    //     foreach ($metadata->getAttributes() as $key => $attrMeta) {
    //         $attribute = $entity->getAttribute($key);
    //         // $formattedKey = $adapter->getExternalFieldKey($key);
    //         $serialized['attributes'][$key] = $this->serializeAttribute($attribute, $attrMeta);
    //     }

    //     $serialized['links'] = ['self' => $adapter->buildUrl($metadata, $entity->getId())];

    //     foreach ($metadata->getRelationships() as $key => $relMeta) {
    //         $relationship = $entity->getRelationship($key);
    //         // $formattedKey = $adapter->getExternalFieldKey($key);
    //         $serialized['relationships'][$key] = $this->serializeRelationship($entity, $relationship, $relMeta, $adapter);
    //     }
    //     return $serialized;
    // }

    /**
     * Serializes an attribute value.
     *
     * @param   Struct\Attribute|null   $attribute
     * @param   AttributeMetadata       $attrMeta
     * @return  mixed
     */
    protected function serializeAttribute($value, AttributeMetadata $attrMeta)
    {
        // @todo Need to determine a better way of converting to serialized version.
        if (null === $value) {
            return $this->typeFactory->convertToSerializedValue($attrMeta->dataType, null);
        }
        // if ('object' === $attrMeta->dataType && $attrMeta->hasAttributes()) {
        //     // If object attributes (sub-attributes) are defined, attempt to convert them to the proper data types.
        //     $serialized = [];
        //     $values = get_object_vars($this->typeFactory->convertToPHPValue('object', $attribute->getValue()));
        //     foreach ($values as $key => $value) {
        //         if (null === $value) {
        //             continue;
        //         }
        //         if (false === $attrMeta->hasAttribute($key)) {
        //             continue;
        //         }
        //         $serialized[$attrMeta->externalKey] = $this->serializeAttribute(new Attribute($key, $value), $attrMeta->getAttribute($key));
        //     }
        //     return $serialized;
        // }
        if (isset($this->typesRequiringConversion[$attrMeta->dataType])) {
            return $this->typeFactory->convertToSerializedValue($attrMeta->dataType, $value);
        }
        return $value;
    }

    protected function serializeHasMany(Model $owner, Collection $relationship = null, JsonApiAdapter $adapter)
    {
        if (null === $relationship) {
            return ['data' => null];
        }
        return $this->serializeCollection($relationship, $adapter);
    }

    protected function serializeHasOne(Model $owner, Model $relationship = null, JsonApiAdapter $adapter)
    {
        if (null === $relationship) {
            return ['data' => null];
        }
        return $this->serialize($relationship, $adapter);
    }

    /**
     * Serializes a relationship value
     *
     * @todo    Need support for meta.
     *
     * @param   Model                       $owner
     * @param   Model                       $relationship
     * @param   RelationshipMetadata        $relMeta
     * @param   AdapterInterface            $adapter
     * @return  array
     */
    protected function serializeRelationship(Model $owner, $relationship = null, RelationshipMetadata $relMeta, JsonApiAdapter $adapter)
    {
        $this->increaseDepth();
        if ($relMeta->isOne()) {
            $serialized = $this->serializeHasOne($owner, $relationship, $adapter);
        } else {
            $serialized = $this->serializeHasMany($owner, $relationship, $adapter);
        }
        $this->decreaseDepth();

        $ownerMeta = $owner->getMetadata();
        $serialized['links'] = [
            'self'      => $adapter->buildUrl($ownerMeta, $owner->getId(), $relMeta->getKey()),
            'related'   => $adapter->buildUrl($ownerMeta, $owner->getId(), $relMeta->getKey(), true),
        ];
        return $serialized;
    }

    /**
     * Encodes the formatted payload array.
     *
     * @param   array   $payload
     * @return  string
     */
    private function encode(array $payload)
    {
        return json_encode($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function serializeError($title, $message, $httpCode)
    {
        return $this->encode([
            'errors'    => [
                ['status' => (String) $httpCode, 'title' => $title, 'detail' => $message],
            ],
        ]);
    }

    /**
     * Increases the serializer depth.
     *
     * @return  self
     */
    protected function increaseDepth()
    {
        $this->depth++;
        return $this;
    }

    /**
     * Decreases the serializer depth.
     *
     * @return  self
     */
    protected function decreaseDepth()
    {
        if ($this->depth > 0) {
            $this->depth--;
        }
        return $this;
    }
}
