<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Attribute;

/**
 * The case's databases sharing the suite connection use the driver's wrap class
 * ({@see \Cycle\Testing\Database\Mock\WrapDriverTrait}): disconnect counting, failing transaction
 * begins, a query callback. Its knobs are reset after every test.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WrapDriver {}
