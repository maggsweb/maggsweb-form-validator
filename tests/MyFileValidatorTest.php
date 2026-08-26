<?php

namespace Maggsweb\Tests;

use Maggsweb\MyFileValidator;
use Maggsweb\Tests\Fixtures\TestableMyFileValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MyFileValidatorTest extends TestCase
{
    private string $uploadDir;

    private string $sourceFile;

    protected function setUp(): void
    {
        $_FILES = [];

        $this->uploadDir = sys_get_temp_dir().'/mfv_test_'.uniqid().'/';
        mkdir($this->uploadDir);

        $this->sourceFile = tempnam(sys_get_temp_dir(), 'mfv_src_');
        file_put_contents($this->sourceFile, 'sample content');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadDir.'*') as $file) {
            unlink($file);
        }
        rmdir($this->uploadDir);

        if (file_exists($this->sourceFile)) {
            unlink($this->sourceFile);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function fakeUploadedFile(array $overrides = []): array
    {
        return array_merge([
            'name'     => 'test.txt',
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $this->sourceFile,
            'size'     => filesize($this->sourceFile),
        ], $overrides);
    }

    // Constructor guard -------------------------------------------------------------------

    public function testMissingFilesKeyIsTreatedAsNoFileSubmitted(): void
    {
        // 'fileupload' was never set in $_FILES at all
        $fileUpload = new MyFileValidator('fileupload');

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertFalse($fileUpload->getError());
    }

    // isRequired() / "no file submitted" ---------------------------------------------------

    public function testNoFileSubmittedIsNotAnErrorByDefault(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0]);

        $fileUpload = new MyFileValidator('fileupload');

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertFalse($fileUpload->getError());
    }

    public function testNoFileSubmittedIsAnErrorWhenRequired(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0]);

        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->isRequired();

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertSame('No file was uploaded.', $fileUpload->getError());
    }

    public function testIsRequiredIsChainable(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0]);

        $fileUpload = new MyFileValidator('fileupload');

        $this->assertFalse($fileUpload->isRequired()->uploadFile());
    }

    // PHP upload error codes ---------------------------------------------------------------

    public static function phpUploadErrorProvider(): array
    {
        return [
            'ini size'   => [UPLOAD_ERR_INI_SIZE, 'The uploaded file exceeds the UPLOAD_MAX_FILESIZE directive in php.ini.'],
            'form size'  => [UPLOAD_ERR_FORM_SIZE, 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'],
            'partial'    => [UPLOAD_ERR_PARTIAL, 'The uploaded file was only partially uploaded.'],
            'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR, 'Missing a temporary folder.'],
            'cant write' => [UPLOAD_ERR_CANT_WRITE, 'Failed to write file to disk.'],
            'extension'  => [UPLOAD_ERR_EXTENSION, 'A PHP extension stopped the file upload.'],
        ];
    }

    #[DataProvider('phpUploadErrorProvider')]
    public function testPhpUploadErrorCodesAreMappedToMessages(int $errorCode, string $expectedMessage): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['error' => $errorCode]);

        $fileUpload = new MyFileValidator('fileupload');

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertSame($expectedMessage, $fileUpload->getError());
    }

    // Directory checks ----------------------------------------------------------------------

    public function testUploadFailsWhenDirectoryDoesNotExist(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile();

        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir.'does-not-exist/']);

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertStringContainsString('was not found', $fileUpload->getError());
    }

    // Allow / deny extension checks -----------------------------------------------------------

    public function testUploadFailsWhenExtensionNotInAllowList(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['name' => 'test.exe']);

        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir, 'allow' => ['txt']]);

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertStringContainsString('is not allowed', $fileUpload->getError());
    }

    public function testUploadFailsWhenExtensionInDenyList(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['name' => 'test.pdf']);

        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir, 'deny' => ['pdf']]);

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertStringContainsString('is not allowed', $fileUpload->getError());
    }

    public function testUploadPassesAllowListWhenExtensionMatches(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['name' => 'test.txt']);

        $fileUpload = new TestableMyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir, 'allow' => ['txt']]);

        $this->assertTrue($fileUpload->uploadFile());
    }

    // Max filesize check ----------------------------------------------------------------------

    public function testUploadFailsWhenFileExceedsMaxFilesize(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['size' => 2 * 1024 * 1024]); // 2Mb

        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir, 'maxFilesize' => 1]); // 1Mb limit

        $this->assertFalse($fileUpload->uploadFile());
        $this->assertStringContainsString('exceeded the allowed filesize', $fileUpload->getError());
    }

    public function testUploadPassesWhenFileWithinMaxFilesize(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['size' => 1024]); // 1Kb

        $fileUpload = new TestableMyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir, 'maxFilesize' => 1]); // 1Mb limit

        $this->assertTrue($fileUpload->uploadFile());
    }

    // Successful upload -----------------------------------------------------------------------

    public function testSuccessfulUploadReportsSuccessMessage(): void
    {
        $_FILES['fileupload'] = $this->fakeUploadedFile(['name' => 'My File.TXT']);

        $fileUpload = new TestableMyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir]);

        $this->assertTrue($fileUpload->uploadFile());
        $this->assertSame('my-file.txt was successfully uploaded', $fileUpload->getSuccess());
        $this->assertFileExists($this->uploadDir.'my-file.txt');
        $this->assertFalse($fileUpload->getError());
    }

    public function testDuplicateFilenameIsMadeUniqueByAppendingANumber(): void
    {
        file_put_contents($this->uploadDir.'test.txt', 'existing file');

        $_FILES['fileupload'] = $this->fakeUploadedFile(['name' => 'test.txt']);

        $fileUpload = new TestableMyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir]);

        $this->assertTrue($fileUpload->uploadFile());
        $this->assertSame('test_1.txt was successfully uploaded', $fileUpload->getSuccess());
        $this->assertFileExists($this->uploadDir.'test_1.txt');
        // Original file untouched
        $this->assertSame('existing file', file_get_contents($this->uploadDir.'test.txt'));
    }

    public function testDuplicateFilenameKeepsIncrementingUntilUnique(): void
    {
        file_put_contents($this->uploadDir.'test.txt', 'a');
        file_put_contents($this->uploadDir.'test_1.txt', 'b');
        file_put_contents($this->uploadDir.'test_2.txt', 'c');

        $_FILES['fileupload'] = $this->fakeUploadedFile(['name' => 'test.txt']);

        $fileUpload = new TestableMyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir]);

        $this->assertTrue($fileUpload->uploadFile());
        $this->assertSame('test_3.txt was successfully uploaded', $fileUpload->getSuccess());
    }

    // getError() default state -----------------------------------------------------------------

    public function testGetErrorReturnsFalseWhenNoErrorHasOccurred(): void
    {
        $fileUpload = new MyFileValidator('fileupload');

        $this->assertFalse($fileUpload->getError());
    }

    public function testGetErrorReturnsFalseForAnUnrecognisedErrorCode(): void
    {
        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->uploadError = 9999;

        $this->assertFalse($fileUpload->getError());
    }

    public function testGetErrorMessageForDirectoryNotWritable(): void
    {
        // Not reliably reproducible cross-platform via real filesystem permissions,
        // so exercise the message mapping directly via the public $uploadError property.
        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions(['path' => $this->uploadDir]);
        $fileUpload->uploadError = 101;

        $this->assertStringContainsString('is not writable', $fileUpload->getError());
    }

    // setOptions() ------------------------------------------------------------------------------

    public function testSetOptionsOverridesDefaults(): void
    {
        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions([
            'path'        => '/custom/path/',
            'allow'       => ['jpg', 'png'],
            'deny'        => ['exe'],
            'maxFilesize' => 5,
        ]);

        $this->assertSame('/custom/path/', $fileUpload->path);
        $this->assertSame(['jpg', 'png'], $fileUpload->allow);
        $this->assertSame(['exe'], $fileUpload->deny);
        $this->assertSame(5, $fileUpload->maxFilesize);
    }
}
