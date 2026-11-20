<?php

declare(strict_types=1);

use Cycle\Testing\Config;
use Cycle\Testing\ORM\OrmPlugin;

return Config::forPackage('cycle/testing', root: __DIR__)
    ->unit('tests/Unit')
    ->feature('tests/Feature')
    ->acceptance('tests/Acceptance', plugins: [new OrmPlugin()])
    ->regression('tests/Regression', plugins: [new OrmPlugin()])
    ->build();
