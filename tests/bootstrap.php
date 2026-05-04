<?php declare(strict_types=1);

/**
 * Bootstrap file for DigitalObject module tests.
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    ['Common', 'DigitalObject'],
    'DigitalObjectTest',
    __DIR__ . '/DigitalObjectTest'
);
