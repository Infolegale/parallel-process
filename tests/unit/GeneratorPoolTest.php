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
use Graze\ParallelProcess\CallbackRun;
use Graze\ParallelProcess\GeneratorPool;
use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
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
    private $generator;

    public function setUp()
    {
        parent::setUp();

        $this->generator = static function () {
            yield Mockery::mock(Process::class)
                ->allows(['stop' => null, 'isStarted' => false, 'isRunning' => false, 'start' => null]);
        };
    }

    public function testGeneratorPoolIsARunInterface()
    {
        $generatorPool = new GeneratorPool(new Pool(), (function () {
        })());
        $this->assertInstanceOf(RunInterface::class, $generatorPool);
    }

    public function testGeneratorPoolIsAPoolInterface()
    {
        $generatorPool = new GeneratorPool(new Pool(), (function () {
        })());
        $this->assertInstanceOf(PoolInterface::class, $generatorPool);
    }

    public function testGeneratorPoolDecoratePoolInterface()
    {
        $mockPool = Mockery::mock(PoolInterface::class)
            ->allows(["hasStarted" => false]);

        $generatorPool = new GeneratorPool($mockPool);
        $this->assertInstanceOf(GeneratorPool::class, $generatorPool);
    }

    public function testGeneratorPoolInitialStateWithProcess()
    {
        $generatorPool = new GeneratorPool(new Pool(), (Closure::bind(function () {
            yield $this->process;
        }, $this, self::class))());

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

        $generatorPool = new GeneratorPool(new Pool(), $generator());

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
        $priorityPool = new GeneratorPool(new Pool());

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

        $generatorPool = new GeneratorPool(new Pool(), $generator());

        $this->assertEquals(1, $generatorPool->count());
    }

    public function testGeneratorPoolWillDelegateStart()
    {
        $generatorPool = new GeneratorPool(new Pool());
        $generatorPool->add((function () {
            yield new CallbackRun(function () {
                return true;
            });
        })());

        $generatorPool->start();

        $this->assertTrue($generatorPool->isSuccessful());
    }

    public function testGeneratorPoolWillStartWithPriorityPool()
    {
        $generatorPool = new GeneratorPool(new PriorityPool());
        $generatorPool->add((function () {
            yield new CallbackRun(function () {
                return true;
            });
        })());

        $generatorPool->start();

        $this->assertTrue($generatorPool->isSuccessful());
    }

    public function testGeneratorPoolWillRunPriorityPoolAsDelegate()
    {
        $generatorPool = new GeneratorPool((new PriorityPool())->setMaxSimultaneous(1));
        $generatorPool->add((function () {
            for ($i = 0; $i < 3; ++$i) {
                $process = Mockery::mock(Process::class);
                $process->shouldReceive('stop');
                $process->shouldReceive('isStarted')
                    ->andReturn(false, false, false, true); // add to pool, check start, check start, started
                $process->shouldReceive('isRunning')->andReturn(false, true, false);
                $process->shouldReceive('start');
                $process->shouldReceive('isSuccessful')->andReturn(true);
                yield $process;
            }
        })());

        $generatorPool->run(0);

        $this->assertTrue($generatorPool->isSuccessful());
    }

    public function testGeneratorPoolWillRunPoolAsDelegate()
    {
        $generatorPool = new GeneratorPool(new Pool());
        $generatorPool->add((function () {
            for ($i = 0; $i < 3; ++$i) {
                yield new CallbackRun(function () {
                    return true;
                });
            }
        })());

        $generatorPool->run(0);

        $this->assertTrue($generatorPool->isSuccessful());
    }

    public function testGeneratorPoolTagsProxy()
    {
        $tags = ['test' => true];
        $decorated = new Pool([], $tags);

        $generatorPool = new GeneratorPool($decorated);

        $this->assertEquals($generatorPool->getTags(), $tags);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGeneratorPoolWillFailToDecorateRunningPool()
    {
        $mockPool = Mockery::mock(PoolInterface::class)
            ->allows(['hasStarted' => 'true']);

        $generatedPool = new GeneratorPool($mockPool);
    }
}
