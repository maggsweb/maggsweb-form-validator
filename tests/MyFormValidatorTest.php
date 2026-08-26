<?php

namespace Maggsweb\Tests;

use LogicException;
use Maggsweb\MyFormValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MyFormValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    // setMethod() / construction ---------------------------------------------------------

    public function testPostModeReadsFromPostSuperglobal(): void
    {
        $_POST['firstname'] = 'Chris';
        $_GET['firstname'] = 'Ignored';

        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname');

        $this->assertSame('Chris', $formVal->getFields()['firstname']);
    }

    public function testGetModeReadsFromGetSuperglobal(): void
    {
        $_GET['firstname'] = 'Chris';
        $_POST['firstname'] = 'Ignored';

        $formVal = new MyFormValidator('get');
        $formVal->validate('firstname');

        $this->assertSame('Chris', $formVal->getFields()['firstname']);
    }

    public function testMethodDefaultsToGet(): void
    {
        $_GET['firstname'] = 'Chris';

        $formVal = new MyFormValidator();
        $formVal->validate('firstname');

        $this->assertSame('Chris', $formVal->getFields()['firstname']);
    }

    public function testUnsubmittedArrayFieldRegistersAsEmptyArray(): void
    {
        $_POST['interests'] = ['PHP', 'MySQL'];

        $formVal = new MyFormValidator('post');
        $formVal->validate('interests');

        $this->assertSame(['PHP', 'MySQL'], $formVal->getFields()['interests']);
    }

    // validate() guard --------------------------------------------------------------------

    public function testValidationMethodBeforeValidateThrows(): void
    {
        $formVal = new MyFormValidator('post');

        $this->expectException(LogicException::class);
        $formVal->isRequired();
    }

    // clean() -------------------------------------------------------------------------

    public function testCleanTrimsAndStripsTagsAndEscapesQuotes(): void
    {
        $_POST['firstname'] = "  <b>Chris</b> \"O'Brien\"  ";

        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname')->clean();

        $this->assertSame('Chris &quot;O&#039;Brien&quot;', $formVal->getFields()['firstname']);
    }

    public function testCleanSanitisesEachElementOfAnArrayField(): void
    {
        $_POST['interests'] = ['PHP', '<b>MySQL</b>', " OOP' "];

        $formVal = new MyFormValidator('post');
        $formVal->validate('interests')->clean();

        $this->assertSame(['PHP', 'MySQL', 'OOP&#039;'], $formVal->getFields()['interests']);
    }

    public function testCleanOnMissingFieldIsNoOp(): void
    {
        $formVal = new MyFormValidator('post');

        // Should not throw, even though 'firstname' was never submitted
        $formVal->validate('firstname')->clean();

        $this->assertArrayNotHasKey('firstname', $formVal->getFields());
    }

    // isRequired() --------------------------------------------------------------------

    public function testIsRequiredFailsOnEmptyString(): void
    {
        $_POST['firstname'] = '';

        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname')->isRequired();

        $this->assertArrayHasKey('firstname', $formVal->getErrors());
    }

    public function testIsRequiredFailsOnWhitespaceOnlyAfterClean(): void
    {
        $_POST['firstname'] = '   ';

        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname')->clean()->isRequired();

        $this->assertArrayHasKey('firstname', $formVal->getErrors());
    }

    public function testIsRequiredFailsWhenFieldNotSubmittedAtAll(): void
    {
        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname')->isRequired();

        $this->assertArrayHasKey('firstname', $formVal->getErrors());
    }

    public function testIsRequiredPassesOnNonEmptyValue(): void
    {
        $_POST['firstname'] = 'Chris';

        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname')->isRequired();

        $this->assertArrayNotHasKey('firstname', $formVal->getErrors());
    }

    public function testIsRequiredFailsOnEmptyArray(): void
    {
        $_POST['interests'] = [];

        $formVal = new MyFormValidator('post');
        $formVal->validate('interests')->isRequired();

        $this->assertArrayHasKey('interests', $formVal->getErrors());
    }

    public function testIsRequiredPassesOnNonEmptyArray(): void
    {
        $_POST['interests'] = ['PHP'];

        $formVal = new MyFormValidator('post');
        $formVal->validate('interests')->isRequired();

        $this->assertArrayNotHasKey('interests', $formVal->getErrors());
    }

    // isEmail() -------------------------------------------------------------------------

    #[DataProvider('validEmailProvider')]
    public function testIsEmailAcceptsValidAddresses(string $email): void
    {
        $_POST['emailadd'] = $email;

        $formVal = new MyFormValidator('post');
        $formVal->validate('emailadd')->isEmail();

        $this->assertArrayNotHasKey('emailadd', $formVal->getErrors());
    }

    public static function validEmailProvider(): array
    {
        return [
            'simple'    => ['chris@example.com'],
            'subdomain' => ['chris@mail.example.co.uk'],
            'plus-tag'  => ['chris+tag@example.com'],
        ];
    }

    #[DataProvider('invalidEmailProvider')]
    public function testIsEmailRejectsInvalidAddresses(string $email): void
    {
        $_POST['emailadd'] = $email;

        $formVal = new MyFormValidator('post');
        $formVal->validate('emailadd')->isEmail();

        $this->assertArrayHasKey('emailadd', $formVal->getErrors());
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'no-at'           => ['chrisexample.com'],
            'no-domain'       => ['chris@'],
            'single-char-tld' => ['chris@example.c'],
        ];
    }

    public function testIsEmailLowercasesResult(): void
    {
        $_POST['emailadd'] = 'Chris@EXAMPLE.com';

        $formVal = new MyFormValidator('post');
        $formVal->validate('emailadd')->isEmail();

        $this->assertSame('chris@example.com', $formVal->getFields()['emailadd']);
    }

    public function testIsEmailSkipsValidationWhenFieldEmptyAndNotRequired(): void
    {
        $_POST['emailadd'] = '';

        $formVal = new MyFormValidator('post');
        $formVal->validate('emailadd')->isEmail();

        $this->assertArrayNotHasKey('emailadd', $formVal->getErrors());
    }

    // isURL() ---------------------------------------------------------------------------

    public function testIsUrlAcceptsHttpsUrl(): void
    {
        $_POST['website'] = 'https://example.com';

        $formVal = new MyFormValidator('post');
        $formVal->validate('website')->isURL();

        $this->assertArrayNotHasKey('website', $formVal->getErrors());
    }

    public function testIsUrlRejectsNonHttpScheme(): void
    {
        $_POST['website'] = 'javascript:alert(1)';

        $formVal = new MyFormValidator('post');
        $formVal->validate('website')->isURL();

        $this->assertArrayHasKey('website', $formVal->getErrors());
    }

    public function testIsUrlRejectsMalformedUrl(): void
    {
        $_POST['website'] = 'not a url';

        $formVal = new MyFormValidator('post');
        $formVal->validate('website')->isURL();

        $this->assertArrayHasKey('website', $formVal->getErrors());
    }

    public function testIsUrlSkipsValidationWhenFieldEmptyAndNotRequired(): void
    {
        $_POST['website'] = '';

        $formVal = new MyFormValidator('post');
        $formVal->validate('website')->isURL();

        $this->assertArrayNotHasKey('website', $formVal->getErrors());
    }

    // isNumber() ------------------------------------------------------------------------

    public function testIsNumberAcceptsInteger(): void
    {
        $_POST['age'] = '42';

        $formVal = new MyFormValidator('post');
        $formVal->validate('age')->isNumber();

        $this->assertArrayNotHasKey('age', $formVal->getErrors());
    }

    public function testIsNumberRejectsDecimal(): void
    {
        $_POST['age'] = '42.5';

        $formVal = new MyFormValidator('post');
        $formVal->validate('age')->isNumber();

        $this->assertArrayHasKey('age', $formVal->getErrors());
    }

    public function testIsNumberRejectsNonNumeric(): void
    {
        $_POST['age'] = 'abc';

        $formVal = new MyFormValidator('post');
        $formVal->validate('age')->isNumber();

        $this->assertArrayHasKey('age', $formVal->getErrors());
    }

    public function testIsNumberAcceptsValueWithinRange(): void
    {
        $_POST['age'] = '42';

        $formVal = new MyFormValidator('post');
        $formVal->validate('age')->isNumber([10, 99]);

        $this->assertArrayNotHasKey('age', $formVal->getErrors());
    }

    public function testIsNumberRejectsValueOutsideRange(): void
    {
        $_POST['age'] = '5';

        $formVal = new MyFormValidator('post');
        $formVal->validate('age')->isNumber([10, 99]);

        $this->assertArrayHasKey('age', $formVal->getErrors());
    }

    // isPassword() ----------------------------------------------------------------------

    public function testIsPasswordRejectsTooShort(): void
    {
        $_POST['password'] = 'ab1';

        $formVal = new MyFormValidator('post');
        $formVal->validate('password')->isPassword(4, 10, false);

        $this->assertArrayHasKey('password', $formVal->getErrors());
        $this->assertStringContainsString('more than', $formVal->getErrors()['password']);
    }

    public function testIsPasswordRejectsTooLong(): void
    {
        $_POST['password'] = 'abcdefghijk';

        $formVal = new MyFormValidator('post');
        $formVal->validate('password')->isPassword(4, 10, false);

        $this->assertArrayHasKey('password', $formVal->getErrors());
        $this->assertStringContainsString('less than', $formVal->getErrors()['password']);
    }

    public function testIsPasswordRejectsMissingUppercaseWhenRequired(): void
    {
        $_POST['password'] = 'abcdefgh';

        $formVal = new MyFormValidator('post');
        $formVal->validate('password')->isPassword(4, 10, true);

        $this->assertArrayHasKey('password', $formVal->getErrors());
        $this->assertStringContainsString('uppercase', $formVal->getErrors()['password']);
    }

    public function testIsPasswordAcceptsValidPassword(): void
    {
        $_POST['password'] = 'abcdefGh';

        $formVal = new MyFormValidator('post');
        $formVal->validate('password')->isPassword(4, 10, true);

        $this->assertArrayNotHasKey('password', $formVal->getErrors());
    }

    public function testIsPasswordReturnsOnlyOneErrorAtATime(): void
    {
        // Too short AND no uppercase - only the length error should be recorded
        $_POST['password'] = 'ab';

        $formVal = new MyFormValidator('post');
        $formVal->validate('password')->isPassword(4, 10, true);

        $this->assertStringContainsString('more than', $formVal->getErrors()['password']);
    }

    public function testIsPasswordAdjustsInvalidMaxCharBelowMinChar(): void
    {
        // maxChar (2) <= minChar (4) so maxChar should be bumped to minChar + 6 = 10
        $_POST['password'] = 'abcdefgh';

        $formVal = new MyFormValidator('post');
        $formVal->validate('password')->isPassword(4, 2, false);

        $this->assertArrayNotHasKey('password', $formVal->getErrors());
    }

    // checkboxGroupRequired() -------------------------------------------------------------

    public function testCheckboxGroupRequiredFailsWhenNotSubmitted(): void
    {
        $formVal = new MyFormValidator('post');
        $formVal->validate('interests')->checkboxGroupRequired(2);

        $this->assertArrayHasKey('interests', $formVal->getErrors());
    }

    public function testCheckboxGroupRequiredFailsWhenTooFewSelected(): void
    {
        $_POST['interests'] = ['PHP'];

        $formVal = new MyFormValidator('post');
        $formVal->validate('interests')->checkboxGroupRequired(2);

        $this->assertArrayHasKey('interests', $formVal->getErrors());
    }

    public function testCheckboxGroupRequiredPassesWhenEnoughSelected(): void
    {
        $_POST['interests'] = ['PHP', 'MySQL'];

        $formVal = new MyFormValidator('post');
        $formVal->validate('interests')->checkboxGroupRequired(2);

        $this->assertArrayNotHasKey('interests', $formVal->getErrors());
    }

    // getErrors() / getFields() / hasErrors() / isValid() ----------------------------------

    public function testHasErrorsAndIsValidReflectErrorState(): void
    {
        $_POST['firstname'] = 'Chris';

        $formVal = new MyFormValidator('post');
        $formVal->validate('firstname')->isRequired();

        $this->assertFalse($formVal->hasErrors());
        $this->assertTrue($formVal->isValid());

        $formVal->validate('surname')->isRequired();

        $this->assertTrue($formVal->hasErrors());
        $this->assertFalse($formVal->isValid());
    }

    // CSRF protection ---------------------------------------------------------------------

    public function testGenerateCsrfTokenCreatesATokenWhenNoneExists(): void
    {
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);

        $token = MyFormValidator::generateCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertSame($_SESSION['csrf_token'], $token);
    }

    public function testGenerateCsrfTokenIsStableAcrossCalls(): void
    {
        $first = MyFormValidator::generateCsrfToken();
        $second = MyFormValidator::generateCsrfToken();

        $this->assertSame($first, $second);
    }

    public function testIsValidCsrfTokenAcceptsMatchingToken(): void
    {
        $token = MyFormValidator::generateCsrfToken();
        $_POST['csrf_token'] = $token;

        $formVal = new MyFormValidator('post');

        $this->assertTrue($formVal->isValidCsrfToken());
        $this->assertArrayNotHasKey('csrf_token', $formVal->getErrors());
    }

    public function testIsValidCsrfTokenRejectsMismatchedToken(): void
    {
        MyFormValidator::generateCsrfToken();
        $_POST['csrf_token'] = 'forged-token';

        $formVal = new MyFormValidator('post');

        $this->assertFalse($formVal->isValidCsrfToken());
        $this->assertArrayHasKey('csrf_token', $formVal->getErrors());
    }

    public function testIsValidCsrfTokenRejectsWhenSessionTokenMissing(): void
    {
        $_POST['csrf_token'] = 'anything';

        $formVal = new MyFormValidator('post');

        $this->assertFalse($formVal->isValidCsrfToken());
    }

    public function testIsValidCsrfTokenRejectsWhenSubmittedValueIsNotAString(): void
    {
        $token = MyFormValidator::generateCsrfToken();
        $_SESSION['csrf_token'] = $token;
        $_POST['csrf_token'] = ['not', 'a', 'string'];

        $formVal = new MyFormValidator('post');

        $this->assertFalse($formVal->isValidCsrfToken());
    }

    public function testIsValidCsrfTokenSupportsCustomFieldName(): void
    {
        $token = MyFormValidator::generateCsrfToken();
        $_POST['my_token'] = $token;

        $formVal = new MyFormValidator('post');

        $this->assertTrue($formVal->isValidCsrfToken('my_token'));
    }
}
