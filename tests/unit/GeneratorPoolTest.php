<?php

/**
 * This file is part of graze/parallel-process.
 *
 * Copyright © 2018 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess\Test\Unit;

use Closure;
use Graze\ParallelProcess\GeneratorPool;
use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

/**
 * Class GeneratorPoolTest
 */
class GeneratorPoolTest extends TestCase
{
    /** @var mixed */
    private $process;

    public function setUp()
    {
        parent::setUp();

        $this->process = Mockery::mock(Process::class)
            ->allows(['stop' => null, 'isStarted' => false, 'isRunning' => false]);
    }

    public function testGeneratorPoolIsARunInterface()
    {
        $generatorPool = new GeneratorPool(new Pool(), function () {
        });
        $this->assertInstanceOf(RunInterface::class, $generatorPool);
    }

    public function testGeneratorPoolIsAPoolInterface()
    {
        $generatorPool = new GeneratorPool(new Pool(), function () {
        });
        $this->assertInstanceOf(PoolInterface::class, $generatorPool);
    }

    public function testGeneratorPoolInitialStateWithProcess()
    {
        $generatorPool = new GeneratorPool(new Pool(), Closure::bind(function () {
            yield $this->process;
        }, $this, self::class));

        $this->assertFalse($generatorPool->isSuccessful());
        $this->assertFalse($generatorPool->isRunning());
        $this->assertFalse($generatorPool->hasStarted());
    }

    /**
     * GeneratorPool Should not be countable
     */
    public function testGeneratorPoolConstructor()
    {
        $generator = static function () {
            for ($i = 0; $i < 2; $i++) {
                yield Mockery::mock(RunInterface::class)
                    ->allows(['isRunning' => false, 'hasStarted' => false, 'addListener' => true, 'getPriority' => 1.0]);
            }
        };

        $generatorPool = new GeneratorPool(new Pool(), $generator);

        $this->assertEquals(1, $generatorPool->count());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddingNonRunInterfaceWillThrowException()
    {
        $nope = Mockery::mock();
        $generatorPool = new GeneratorPool(new Pool());
        $generatorPool->add($nope);
    }

    public function testGeneratorPoolInitialStateWithNoRuns()
    {
        $priorityPool = new GeneratorPool(new PriorityPool());

        $this->assertFalse($priorityPool->isSuccessful(), 'should not be successful');
        $this->assertFalse($priorityPool->isRunning(), 'should not be running');
        $this->assertFalse($priorityPool->hasStarted(), 'should not be started');
    }

    public function testGeneratorPoolAddingGenerator()
    {
        $generator = function () {
            for ($i = 0; $i < 2; $i++) {
                yield Mockery::mock(RunInterface::class)
                    ->allows(['isRunning' => false, 'hasStarted' => false, 'addListener' => true, 'getPriority' => 1.0]);
            }
        };

        $generatorPool = new GeneratorPool(new Pool());
        $generatorPool->add(($generator));

        $this->assertEquals(1, $generatorPool->count());
    }
}
