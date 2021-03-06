<?php

namespace RAPL\RAPL\Serializer;

use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use RAPL\RAPL\Mapping\ClassMetadata;
use RAPL\RAPL\Types\Type;
use RAPL\RAPL\UnitOfWork;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class Serializer implements SerializerInterface
{
    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * @var ClassMetadataFactory
     */
    private $classMetadataFactory;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @param ClassMetadata        $metadata
     * @param UnitOfWork           $unitOfWork
     * @param ClassMetadataFactory $metadataFactory
     */
    public function __construct(ClassMetadata $metadata, UnitOfWork $unitOfWork, ClassMetadataFactory $metadataFactory)
    {
        $this->classMetadata        = $metadata;
        $this->unitOfWork           = $unitOfWork;
        $this->classMetadataFactory = $metadataFactory;

        $normalizers      = [new GetSetMethodNormalizer()];
        $encoders         = [new JsonEncoder()];
        $this->serializer = new SymfonySerializer($normalizers, $encoders);
    }

    /**
     * Deserializes serialized data
     *
     * @param string  $data
     * @param boolean $isCollection
     * @param array   $envelopes
     *
     * @return array
     */
    public function deserialize($data, $isCollection, array $envelopes = [])
    {
        $data = $this->decode($data);
        $data = $this->unwrap($data, $envelopes);

        if (!$isCollection) {
            $data = [$data];
        }

        $hydratedEntities = [];

        foreach ($data as $entityData) {
            $entityData = $this->mapFromSerialized($entityData);

            $this->hydrateSingleEntity($entityData, $hydratedEntities);
        }

        return $hydratedEntities;
    }

    /**
     * @param string $data
     *
     * @return array
     */
    private function decode($data)
    {
        return $this->serializer->decode($data, $this->classMetadata->getFormat());
    }

    /**
     * @param array $data
     * @param array $result
     */
    private function hydrateSingleEntity(array $data, array &$result)
    {
        $entity   = $this->unitOfWork->createEntity($this->classMetadata->getName(), $data);
        $result[] = $entity;
    }

    /**
     * Unwraps the data from its envelopes
     *
     * @param array $data
     * @param array $envelopes
     *
     * @return array
     */
    private function unwrap(array $data, array $envelopes)
    {
        foreach ($envelopes as $envelope) {
            if (isset($data[$envelope])) {
                $data = $data[$envelope];
            } else {
                return [];
            }
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function mapFromSerialized(array $data)
    {
        $mappedEntityData = [];

        foreach ($data as $serializedName => $value) {
            if ($this->classMetadata->hasField($this->classMetadata->getFieldName($serializedName))) {
                $fieldName    = $this->classMetadata->getFieldName($serializedName);
                $fieldMapping = $this->classMetadata->getFieldMapping($fieldName);

                if (isset($fieldMapping['association'])) {
                    $embedded = [];

                    $associationMetadata   = $this->classMetadataFactory->getMetadataFor($fieldMapping['targetEntity']);
                    $associationSerializer = new Serializer(
                        $associationMetadata,
                        $this->unitOfWork,
                        $this->classMetadataFactory
                    );

                    if ($fieldMapping['association'] === ClassMetadata::EMBED_ONE) {
                        if (is_array($value)) {
                            $associationData = $associationSerializer->mapFromSerialized($value);
                            $associationSerializer->hydrateSingleEntity($associationData, $embedded);

                            $value = reset($embedded);
                        } else {
                            $value = null;
                        }
                    }
                } else {
                    $type  = Type::getType($fieldMapping['type']);
                    $value = $type->convertToPhpValue($value);
                }

                $mappedEntityData[$fieldName] = $value;
            }
        }

        return $mappedEntityData;
    }
}
