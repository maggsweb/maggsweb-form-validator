<?php

namespace Maggsweb\Tests\Fixtures;

use Maggsweb\MyFormValidator;

/**
 * _verifyRecaptcha() normally calls Google's siteverify endpoint over HTTP, which
 * unit tests must not depend on. This subclass swaps it for a canned response set
 * via $recaptchaResponse.
 */
class TestableMyFormValidator extends MyFormValidator
{
    public array $recaptchaResponse = [];

    protected function _verifyRecaptcha(string $secretKey, string $token): array
    {
        return $this->recaptchaResponse;
    }
}
