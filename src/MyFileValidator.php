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
     * $fieldname
     * @desc Form field name
     * @var string
     */
    private $fieldname;
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
    private $fileName;
    /*
     * $fileExtension
     * @desc lowercase file extension
     * @var string
     */
    private $fileExtension;

    /*
     * $path
     * @desc Absolute path to writable directory
     * @var string
     */
    public $path;

    /*
     * $allow
     * @desc Array of file extensions to 'allow'
     * @var array
     */
    public $allow;

    /*
     * $deny
     * @desc Array of file extensions to 'deny'
     * @var array
     */
    public $deny;

    /*
     * $maxFilesize
     * @desc maximum upload file size (in Mb) or false;
     * @var int
     */
    public $maxFilesize;

    /*
     * $uploadError
     * @param $fieldname
     * @var string
     */
    public $uploadError;

    /**
     * MyFileValidator constructor.
     *
     * @param string $fieldname
     */
    public function __construct($fieldname)
    {
        $this->fieldname = $fieldname;
        $this->fileArray = $_FILES[$fieldname];
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
    public function setOptions(array $optionsArray)
    {
        foreach ($optionsArray as $name => $value) {
            $this->$name = $value;
        }
    }

    public function uploadFile()
    {

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
    public function getSuccess()
    {
        return "{$this->fileName}.{$this->fileExtension} was successfully uploaded";
    }

    private function makeUniqueFilename()
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
    private function incrementFilename($digit)
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
    public function getError()
    {
        return $this->_getErrorMessage($this->uploadError);
    }

    /**
     * @param int $errorNumber
     *
     * @return bool|string
     */
    private function _getErrorMessage($errorNumber)
    {
        switch ($errorNumber) {
            // Standard File Upload errors
            case 1:  return 'The uploaded file exceeds the UPLOAD_MAX_FILESIZE directive in php.ini.';
            case 2:  return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
            case 3:  return 'The uploaded file was only partially uploaded.';
            case 4:  return 'No file was uploaded.';
            case 6:  return 'Missing a temporary folder.';
            case 7:  return 'Failed to write file to disk.';
            case 8:  return 'A PHP extension stopped the file upload.';
            // Additional check errors
            case 100: return "The directory '$this->path' was not found";
            case 101: return "The directory '$this->path' is not writable";
            case 102: return "The uploaded file extension '$this->fileExtension' is not allowed";
            case 103: return "The uploaded file exceeded the allowed filesize of {$this->maxFilesize}Mb";
            case 104: return 'Error moving unloaded file';

            default: return false;
        }
    }

    /**
     * @return string
     */
    private function _cleanFilename()
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
    private function _fileExtension()
    {
        $fileNameArray = explode('.', $this->fileArray['name']);

        return strtolower(array_pop($fileNameArray));
    }
}
