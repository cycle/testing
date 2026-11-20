<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\Postgres\PostgresDriver;

class PostgresWrapDriver extends PostgresDriver implements WrapDriverInterface
{
    use WrapDriverTrait;
}
