<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\SQLite\SQLiteDriver;

class SQLiteWrapDriver extends SQLiteDriver implements WrapDriverInterface
{
    use WrapDriverTrait;
}
