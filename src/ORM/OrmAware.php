<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\ORM\ORMInterface;

/**
 * A test case class that receives a fresh ORM before every test.
 */
interface OrmAware
{
    public function attachOrm(ORMInterface $orm): void;

    /**
     * The ORM the test ended with (it may have replaced the attached one, e.g. with another schema);
     * the heap check runs on it.
     */
    public function currentOrm(): ORMInterface;
}
