<?php

require 'vendor/autoload.php';

use Maggsweb\MyFormValidator;

/**
 * Instantiate the MyFormValidator for use
 * Flag method as POST.
 */
$formVal = new MyFormValidator('post');

// ----------------------------------------------------------------
// Validation Process Methods  ------------------------------------
// ----------------------------------------------------------------

/*
 * Sanitise field
 * This should be added to all fields that include any sort of user input/selection
 */
$formVal->validate('some-field-name')->clean();

/*
 * Mandatory field
 * Flag fields as mandatory
 */
$formVal->validate('some-field-name')->isRequired();

/*
 * Validate input value as a valid email address
 */
$formVal->validate('some-field-name')->isEmail();

/*
 * Validate input value as a password, using set rules set
 * - MinCharacters
 * - Max Characters
 * - Require Uppercase Character
 */
$formVal->validate('some-field-name')->isPassword(6, 20, false);

/*
 * Validate as a URL
 */
$formVal->validate('some-field-name')->isURL();

/*
 * Validate as numeric, or zero
 * - optionally validating within a set range
 */
$formVal->validate('some-field-name')->isNumber();
//$formVal->validate('some-field-name')->isNumber(array(10,99));

/*
 * Ensure that X number of check-box options have been selected
 */
$formVal->validate('some-checkbox-group-name')->checkboxGroupRequired(2);

// ----------------------------------------------------------------
// Return methods  --------------------------------------------
// ----------------------------------------------------------------

/**
 * Get an array of error messages in:
 *  $fieldname => $message.
 */
$errors = $formVal->getErrors();

/**
 * Get an array of cleaned form fields in:
 *  $fieldname => $value.
 */
$fields = $formVal->getFields();
