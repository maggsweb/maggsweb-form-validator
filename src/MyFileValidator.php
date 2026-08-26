<?php

namespace Maggsweb;

/**
 * MyFileValidator Class.
 *
 * @category  File Upload Validation
 *
 * @author    Chris Maggs <git@maggsweb.co.uk>
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 **/
class MyFileValidator
{
    /*
     * $fileArray
     * @desc Global uploaded file array
     * @var array
     */
    private $fileArray;

    /*
     * $fileName
     * @desc cleaned generated filename
     * @var string
     */
    private string $fileName;
    /*
     * $fileExtension
     * @desc lowercase file extension
     * @var string
     */
    private string $fileExtension;

    /*
     * $path
     * @desc Absolute path to writable directory
     * @var string
     */
    public string $path;

    /*
     * $allow
     * @desc Array of file extensions to 'allow'
     * @var array|bool
     */
    public array|bool $allow;

    /*
     * $deny
     * @desc Array of file extensions to 'deny'
     * @var array|bool
     */
    public array|bool $deny;

    /*
     * $maxFilesize
     * @desc maximum upload file size (in Mb) or false;
     * @var int
     */
    public int $maxFilesize;

    /*
     * $uploadError
     * @param $fieldname
     * @var int|false
     */
    public int|false $uploadError;

    /*
     * $required
     * @desc Whether a file upload is mandatory
     * @var bool
     */
    private bool $required = false;

    /**
     * MyFileValidator constructor.
     *
     * @param string $fieldname
     */
    public function __construct(string $fieldname)
    {
        $this->fileArray = $_FILES[$fieldname] ?? ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'tmp_name' => '', 'size' => 0];
        $this->fileName = $this->_cleanFilename();
        $this->fileExtension = $this->_fileExtension();

        // Set default options
        $this->path = '/';
        $this->maxFilesize = 10;  // 10Mb
        $this->uploadError = false;
        $this->allow = false;
        $this->deny = false;
    }

    /**
     * @param array $optionsArray
     */
    public function setOptions(array $optionsArray): void
    {
        foreach ($optionsArray as $name => $value) {
            $this->$name = $value;
        }
    }

    /**
     * Flag the file upload as mandatory.
     *
     * @return $this
     */
    public function isRequired(): static
    {
        $this->required = true;

        return $this;
    }

    public function uploadFile(): bool
    {
        // No file was submitted
        if ($this->fileArray['error'] === UPLOAD_ERR_NO_FILE) {
            if ($this->required) {
                $this->uploadError = UPLOAD_ERR_NO_FILE;
            }

            return false;
        }

        // File upload error
        if ($this->fileArray['error']) {
            $this->uploadError = $this->fileArray['error'];

            return false;
        }

        // Check directory
        if (!is_dir($this->path)) {
            $this->uploadError = 100;

            return false;
        }

        // Check writeble directory
        if (!is_writable($this->path)) {
            $this->uploadError = 101;

            return false;
        }

        // Check allowed file extension
        if (is_array($this->allow)) {
            if (!in_array($this->fileExtension, $this->allow)) {
                $this->uploadError = 102;

                return false;
            }
        }

        // Check denied file extension
        if (is_array($this->deny)) {
            if (in_array($this->fileExtension, $this->deny)) {
                $this->uploadError = 102;

                return false;
            }
        }

        // Size
        $b = $this->fileArray['size'];
        $kb = $b / 1024;
        $mb = $kb / 1024;
        if ($mb > $this->maxFilesize) {
            $this->uploadError = 103;

            return false;
        }

        // Check if filename already exists
        if (file_exists($this->path.$this->fileName.'.'.$this->fileExtension)) {
            $this->makeUniqueFilename();
        }

        // Copy Files
        if (!move_uploaded_file($this->fileArray['tmp_name'], $this->path.$this->fileName.'.'.$this->fileExtension)) {
            $this->uploadError = 104;

            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function getSuccess(): string
    {
        return "{$this->fileName}.{$this->fileExtension} was successfully uploaded";
    }

    private function makeUniqueFilename(): void
    {
        // Create new filename with numeric part
        $this->fileName .= '_1';
        // Incremented filename exists
        if (file_exists($this->path.$this->fileName.'.'.$this->fileExtension)) {
            $this->incrementFilename(2);
        }
    }

    /**
     * @param int $digit
     */
    private function incrementFilename(int $digit): void
    {
        // New incremented filename
        $tmp = explode('_', $this->fileName);
        array_pop($tmp);
        $this->fileName = implode('_', $tmp).'_'.$digit;

        // Incremented filename exists
        if (file_exists($this->path.$this->fileName.'.'.$this->fileExtension)) {
            // Increment until unique
            $this->incrementFilename($digit + 1);
        }
    }

    /**
     * @return bool|string
     */
    public function getError(): bool|string
    {
        return $this->_getErrorMessage($this->uploadError);
    }

    /**
     * @param int $errorNumber
     *
     * @return bool|string
     */
    private function _getErrorMessage(int $errorNumber): bool|string
    {
        return match ($errorNumber) {
            1       => 'The uploaded file exceeds the UPLOAD_MAX_FILESIZE directive in php.ini.',
            2       => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            3       => 'The uploaded file was only partially uploaded.',
            4       => 'No file was uploaded.',
            6       => 'Missing a temporary folder.',
            7       => 'Failed to write file to disk.',
            8       => 'A PHP extension stopped the file upload.',
            100     => "The directory '$this->path' was not found",
            101     => "The directory '$this->path' is not writable",
            102     => "The uploaded file extension '$this->fileExtension' is not allowed",
            103     => "The uploaded file exceeded the allowed filesize of {$this->maxFilesize}Mb",
            104     => 'Error moving unloaded file',
            default => false,
        };
    }

    /**
     * @return string
     */
    private function _cleanFilename(): string
    {
        $fileNameArray = explode('.', $this->fileArray['name']);

        array_pop($fileNameArray);

        $dirtyFileName = implode('', $fileNameArray);
        $dirtyFileName = preg_replace('/\s+/', '-', $dirtyFileName);
        $dirtyFileName = preg_replace("/[^a-zA-Z0-9\-\_]/", '', $dirtyFileName);
        $dirtyFileName = strtolower($dirtyFileName);
        $cleanFilename = trim($dirtyFileName);

        return $cleanFilename;
    }

    /**
     * @return string
     */
    private function _fileExtension(): string
    {
        $fileNameArray = explode('.', $this->fileArray['name']);

        return strtolower(array_pop($fileNameArray));
    }
}
