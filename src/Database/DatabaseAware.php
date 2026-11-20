<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

/**
 * A test case class that receives the database services of its suite.
 *
 * Testo creates one instance per case, not per test, so the context is attached once, before the first
 * hook or test of the case runs. Keep per-test state in `#[BeforeTest]`, never across tests.
 */
interface DatabaseAware
{
    public function attachDatabase(DatabaseContext $context): void;
}
