<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\MySQL\MySQLDriver;

class MySQLWrapDriver extends MySQLDriver implements WrapDriverInterface
{
    use WrapDriverTrait;
}
