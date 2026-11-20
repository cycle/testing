<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\SQLServer\SQLServerDriver;

class SQLServerWrapDriver extends SQLServerDriver implements WrapDriverInterface
{
    use WrapDriverTrait;
}
