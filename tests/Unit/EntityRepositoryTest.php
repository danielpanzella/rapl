<?php

namespace RAPL\Tests\Unit;

use Mockery;
use PHPUnit_Framework_TestCase;
use RAPL\RAPL\EntityRepository;
use RAPL\RAPL\Mapping\ClassMetadata;
use RAPL\RAPL\Persister\EntityPersister;
use RAPL\Tests\Fixtures\Entities\Book;

class EntityRepositoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var EntityRepository
     */
    private $entityRepository;

    /**
     * @var Mockery\MockInterface|EntityPersister
     */
    private $entityPersister;

    /**
     * @var Mockery\MockInterface|ClassMetadata
     */
    private $classMetadata;

    protected function setUp()
    {
        $this->entityPersister  = Mockery::mock('RAPL\RAPL\Persister\EntityPersister');
        $this->classMetadata    = Mockery::mock('RAPL\RAPL\Mapping\ClassMetadata');
        $this->entityRepository = new EntityRepository($this->entityPersister, $this->classMetadata);
    }

    public function testFind()
    {
        $object = new Book();

        $this->entityPersister->shouldReceive('loadById')->once()->with(['id' => 3])->andReturn($object);
        $this->classMetadata->shouldReceive('getIdentifierFieldNames')->once()->andReturn(['id']);

        $this->assertSame($object, $this->entityRepository->find(3));
    }

    public function testFindAll()
    {
        $result = [new Book()];

        $this->entityPersister->shouldReceive('loadAll')->once()->with([], null, null, null)->andReturn($result);

        $this->assertSame($result, $this->entityRepository->findAll());
    }

    public function testFindBy()
    {
        $result = [new Book()];

        $criteria = ['id' => 3];
        $orderBy  = ['name' => 'asc'];
        $limit    = 10;
        $offset   = 20;

        $this->entityPersister
            ->shouldReceive('loadAll')
            ->once()
            ->with($criteria, $orderBy, $limit, $offset)
            ->andReturn($result);

        $this->assertSame($result, $this->entityRepository->findBy($criteria, $orderBy, $limit, $offset));
    }

    public function testFindOneBy()
    {
        $object = new Book();

        $result = [$object];

        $criteria = ['id' => 3];

        $this->entityPersister->shouldReceive('loadAll')->once()->with($criteria, null, null, null)->andReturn($result);

        $this->assertSame($object, $this->entityRepository->findOneBy($criteria));
    }

    public function testGetClassName()
    {
        $className = 'FooBar';

        $this->classMetadata->shouldReceive('getName')->andReturn($className)->once();

        $this->assertSame($className, $this->entityRepository->getClassName());
    }
}
