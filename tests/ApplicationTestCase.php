<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Testing\ApplicationTestCase as EzPhpApplicationTestCase;

/**
 * Base class for broadcast module tests that need a bootstrapped Application.
 *
 * The default getBasePath() from EzPhp\Testing\ApplicationTestCase creates a
 * temporary directory with an empty config/ subdirectory. This satisfies
 * ConfigLoader without requiring a real application structure, and keeps all
 * service bindings lazy.
 *
 * Override configureApplication() to register providers before bootstrap.
 *
 * @package Tests
 */
abstract class ApplicationTestCase extends EzPhpApplicationTestCase
{
}
