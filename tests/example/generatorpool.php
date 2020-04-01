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

$composer = require_once __DIR__ . '/../../vendor/autoload.php';
$composer->setUseIncludePath(true);

use Graze\ParallelProcess\Display\Table;
use Graze\ParallelProcess\GeneratorPool;
use Graze\ParallelProcess\ProcessRun;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;

$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_VERY_VERBOSE);

$pool = new \Graze\ParallelProcess\PriorityPool();
$pool->setMaxSimultaneous(5);

$generator = static function () use ($pool) {
    for ($i = 0; $i < 5; $i++) {
        $time = $i + 1;
        yield new ProcessRun(new Process(sprintf('for i in `seq 1 %d` ; do date ; sleep 1 ; done', $time)), ['sleep' => $time]);
    }
};

$generatorPool = new GeneratorPool($pool, $generator);
var_dump($generatorPool->count());
$lines = new Table($output, $generatorPool);
$lines->run();
