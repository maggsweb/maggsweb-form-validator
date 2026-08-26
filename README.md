[![StyleCI](https://github.styleci.io/repos/108392626/shield?branch=master)](https://github.styleci.io/repos/108392626)

# Maggsweb Form Validator

An easy-to-use PHP Form Validation Class

<hr>

### Table of Contents
**[Installation](#installation)**  
**[Initialization](#initialization)**  
**[CSRF Protection](#csrf-protection)**  
**[Validation Methods](#validation-methods)**  
**[File Upload Method](#file-upload-method)**  
**[Return Methods](#return-methods)**  

<hr>

## Installation

```
composer require maggsweb/maggsweb-form-validator
```

## Initialization

This class is instantiated after submission of a form.

Fields are registered within the class, so that Validation methods can be performed and arrays of errors/values returned

```php

require 'vendor/autoload.php';

use Maggsweb\MyFormValidator;

/**
 * Instantiate the MyFormValidator for use
 * Flag method as POST
 */
$formVal = new MyFormValidator('post');

```

## CSRF Protection

Requires an active session - `session_start()` must be called before the token is generated or checked.

Generate a token to render as a hidden field on the form:

```php

session_start();

$csrfToken = MyFormValidator::generateCsrfToken();

```

```html
<input type="hidden" name="csrf_token" value="<?=$csrfToken ?>" />
```

On submission, check it before trusting any other submitted data:

```php

$formVal = new MyFormValidator('post');

if ($formVal->isValidCsrfToken()) {
    // ...proceed with the rest of validation
} else {
    // Token missing/invalid - $formVal->getErrors() now contains a 'csrf_token' message
}

```

## Validation Methods

All of these methods can be chained if required.

eg: $formVal->validate('some-field-name')->clean()->isRequired()->isEmail();

```php

/**
 * Sanitise field
 * This should be added to all fields that include any sort of user input/selection
 */
$formVal->validate('some-field-name')->clean();

/**
 * Mandatory field
 * Flag fields as mandatory
 */
$formVal->validate('some-field-name')->isRequired();

/**
 * Validate input value as a valid email address
 */
$formVal->validate('some-field-name')->isEmail();

/**
 * Validate input value as a password, using set rules set
 * - MinCharacters
 * - Max Characters
 * - Require Uppercase Character
 */
$formVal->validate('some-field-name')->isPassword(6,20,false);

/**
 * Validate as a URL
 */
$formVal->validate('website')->clean()->isURL();

/**
 * Validate as numeric, or zero
 * - optionally validating within a set range
 */
$formVal->validate('age')->isNumber();
//$formVal->validate('age')->isNumber(array(10,99));

/**
 * Ensure that X number of check-box options have been selected
 * clean() sanitises each selected value in the array
 */
$formVal->validate('some-checkbox-group-name')->clean()->checkboxGroupRequired(2);

```




## File Upload Method

File uploads are optional by default - if no file is submitted, `uploadFile()` simply
returns `false` without recording an error. Call `isRequired()` to make the field mandatory.

```php

/**
 * Optional
 * --------
 * Override default options to allow configuration
 */
$options = [];
$options['path']          = 'uploads/';
$options['allow']         = array('txt');
$options['deny']          = array('pdf');
$options['maxFilesize']   = 1; // 1Mb

/**
 * Process File Upload, 
 *  returning an error to add to the existing array
 *  or a success message
 */

$fileUpload = new MyFileValidator('fileupload');
$fileUpload->setOptions($options);

/**
 * Optional
 * --------
 * Flag the file upload as mandatory
 */
//$fileUpload->isRequired();

if($fileUpload->uploadFile()){
    $fields['fileupload'] = $fileUpload->getSuccess();
} else {
    $errors['fileupload'] = $fileUpload->getError();
}

 ```

## Return Methods

The getErrors() and getValues() methods are available to the processing file to return arrays for processing

```php

/**
 * Get an array of error messages in:
 *  $fieldname => $message
 * 
 */
$errors = $formVal->getErrors();


/**
 * Get an array of cleaned form fields in:
 *  $fieldname => $value
 */
$fields = $formVal->getFields();

```

See fully working example in example.php
