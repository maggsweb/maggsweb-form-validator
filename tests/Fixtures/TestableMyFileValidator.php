<?php

namespace Maggsweb\Tests\Fixtures;

use Maggsweb\MyFileValidator;

/**
 * move_uploaded_file() only ever succeeds for a file genuinely uploaded via HTTP,
 * so it can't be exercised from a unit test. This subclass swaps it for copy(),
 * which behaves identically for every check that runs before the move.
 */
class TestableMyFileValidator extends MyFileValidator
{
    protected function _moveUploadedFile(string $tmpName, string $destination): bool
    {
        return copy($tmpName, $destination);
    }
}
