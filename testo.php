<?php

declare(strict_types=1);

use Cycle\Testing\Config;
use Cycle\Testing\ORM\OrmPlugin;

return Config::forPackage('cycle/testing', root: __DIR__)
    ->unit('tests/Unit')
    ->acceptance('tests/Acceptance', plugins: [new OrmPlugin()])
    ->build();
