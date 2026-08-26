<?php

namespace Maggsweb;

use LogicException;

/**
 * MyFormValidator Class.
 *
 * @category  Form Validation
 *
 * @author    Chris Maggs <git@maggsweb.co.uk>
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 **/
class MyFormValidator
{
    /**
     * @var array
     */
    private array $method;

    /**
     * @var string
     */
    private string $field = '';

    /**
     * @var array
     */
    private array $errors = [];

    /**
     * @var array
     */
    private array $fields = [];

    /**
     * MyFormValidator constructor.
     *
     * @param string $method
     */
    public function __construct(string $method = 'get')
    {
        $this->setMethod($method);
    }

    // PRIVATE METHODS  /////////////////////////////////////////////////////////////////

    /**
     * Set form fields, defaulting each registered field to an empty value.
     */
    private function _registerFields(): void
    {
        $this->fields = [];
        foreach ($this->method as $key => $value) {
            $this->fields[$key] = is_array($value) ? [] : '';
        }
    }

    /**
     * Guard against validation methods being called before validate().
     *
     * @throws LogicException
     */
    private function _requireFieldSet(): void
    {
        if ($this->field === '') {
            throw new LogicException('validate($field) must be called before any validation method.');
        }
    }

    /**
     * Whether the current field's value counts as empty.
     *
     * @param mixed $value
     * @return bool
     */
    private function _isEmpty(mixed $value): bool
    {
        return is_array($value) ? empty($value) : !strlen($value);
    }

    /**
     * Whether the current field was submitted and has a non-empty value.
     */
    private function _hasValue(): bool
    {
        return isset($this->method[$this->field]) && !$this->_isEmpty($this->fields[$this->field]);
    }

    // PUBLIC METHODS  /////////////////////////////////////////////////////////////////

    /**
     * Set form method, and re-register fields from it.
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): static
    {
        $this->method = trim(strtolower($method)) == 'post' ? $_POST : $_GET;

        $this->_registerFields();

        return $this;
    }

    /**
     * Return an array of errors for processing.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Return an array of validated fields for processing.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Whether any validation errors have been recorded.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Whether all validation performed so far has passed.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function validate(string $field): static
    {
        // Set original value on $this->fields
        if (isset($this->fields[$field])) {
            $this->fields[$field] = $this->method[$field];
        }

        // Set field name for further validation
        $this->field = $field;

        return $this;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    //  Public Validation Methods   ///////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @return $this
     */
    public function clean(): static
    {
        $this->_requireFieldSet();

        if (isset($this->method[$this->field])) {
            $value = $this->method[$this->field];

            // Overwrite with clean value(s); checkbox/multi-select groups arrive as arrays
            $this->fields[$this->field] = is_array($value)
                ? array_map([$this, '_sanitize'], $value)
                : $this->_sanitize($value);
        }

        return $this;
    }

    /**
     * Sanitise a single scalar input value.
     *
     * @param string $value
     *
     * @return string
     */
    private function _sanitize(string $value): string
    {
        $value = trim($value);
        $value = strip_tags($value);

        // Escape quotes only, so values remain safe inside an HTML attribute
        // without corrupting '&' in URLs/emails ahead of isURL()/isEmail().
        return str_replace(['"', "'"], ['&quot;', '&#039;'], $value);
    }

    /**
     * @return $this
     */
    public function isRequired(): static
    {
        $this->_requireFieldSet();

        if (isset($this->method[$this->field])) {
            if ($this->_isEmpty($this->fields[$this->field])) {
                $this->errors[$this->field] = 'This field is required';
            }
        } else {
            $this->errors[$this->field] = 'This field is required';
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function isEmail(): static
    {
        $this->_requireFieldSet();

        if ($this->_hasValue()) {
            $EMAIL = filter_var($this->fields[$this->field], FILTER_SANITIZE_EMAIL);
            $tld = strrchr($EMAIL, '.');

            // FILTER_VALIDATE_EMAIL accepts single-character TLDs (e.g. "example.c"),
            // which don't exist in real DNS; require at least 2 characters.
            if (filter_var($EMAIL, FILTER_VALIDATE_EMAIL) === false || strlen($tld) < 3) {
                $this->errors[$this->field] = 'Email address is invalid';
            }
            $this->fields[$this->field] = strtolower($EMAIL);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function isURL(): static
    {
        $this->_requireFieldSet();

        if ($this->_hasValue()) {
            $URL = filter_var($this->fields[$this->field], FILTER_SANITIZE_URL);
            $scheme = strtolower((string) parse_url($URL, PHP_URL_SCHEME));

            if (filter_var($URL, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                $this->errors[$this->field] = 'URL is invalid';
            }
            $this->fields[$this->field] = strtolower($URL);
        }

        return $this;
    }

    /**
     * Validates the field as an integer (decimal values are not accepted).
     *
     * @param bool|array $withinRange
     *
     * @return $this
     */
    public function isNumber(bool|array $withinRange = false): static
    {
        $this->_requireFieldSet();

        if ($this->_hasValue()) {
            $NUMBER = $this->fields[$this->field];

            // Integer
            if (filter_var($NUMBER, FILTER_VALIDATE_INT) === false) {
                $this->errors[$this->field] = 'Value is not numeric';

                return $this;
            }

            // Range
            if (is_array($withinRange)) {
                list($min, $max) = $withinRange;
                if (filter_var($NUMBER, FILTER_VALIDATE_INT, ['options' => ['min_range'=>$min, 'max_range'=>$max]]) === false) {
                    $this->errors[$this->field] = "Value is not within the range of $min - $max";
                }
            }
        }

        return $this;
    }

    /**
     * @param int $minChar
     * @param int $maxChar
     * @param bool $forceUpperCase
     *
     * @return $this
     */
    public function isPassword(int $minChar = 6, int $maxChar = 20, bool $forceUpperCase = false): static
    {
        $this->_requireFieldSet();

        if ($maxChar <= $minChar) {
            $maxChar = $minChar + 6;
        }

        if ($this->_hasValue()) {
            if (strlen($this->fields[$this->field]) < $minChar) {
                $this->errors[$this->field] = "Passwords must be more than $minChar characters";

                return $this;
            }
            if (strlen($this->fields[$this->field]) > $maxChar) {
                $this->errors[$this->field] = "Passwords must be less than $maxChar characters";

                return $this;
            }
            if ($forceUpperCase) {
                if (!preg_match('/[A-Z]+/', $this->fields[$this->field])) {
                    $this->errors[$this->field] = 'Passwords must contain an uppercase character';

                    return $this;
                }
            }
        }

        return $this;
    }

    /**
     * @param int $requiredSelections
     *
     * @return $this
     */
    public function checkboxGroupRequired(int $requiredSelections = 1): static
    {
        $this->_requireFieldSet();

        if (isset($this->method[$this->field])) {
            if (is_array($this->method[$this->field])) {
                if (count($this->method[$this->field]) < $requiredSelections) {
                    $this->errors[$this->field] = 'You must select '.$requiredSelections.' options';
                }
            }
        } else {
            $this->errors[$this->field] = 'You must select '.$requiredSelections.' options';
        }

        return $this;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    //  CSRF Protection   //////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Return the CSRF token for the current session, generating one if none exists yet,
     * so it stays stable across repeated renders of the same form (e.g. after a failed
     * validation). Embed the return value in a hidden form field.
     *
     * Requires an active session (session_start() must already have been called).
     *
     * @return string
     */
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a submitted CSRF token against the one stored in the session.
     * Records an error against $fieldName on failure.
     *
     * Requires an active session (session_start() must already have been called).
     *
     * @param string $fieldName
     *
     * @return bool
     */
    public function isValidCsrfToken(string $fieldName = 'csrf_token'): bool
    {
        $submitted = $this->method[$fieldName] ?? '';

        $isValid = !empty($_SESSION['csrf_token']) && is_string($submitted) && hash_equals($_SESSION['csrf_token'], $submitted);

        if (!$isValid) {
            $this->errors[$fieldName] = 'Your session has expired, please try again';
        }

        return $isValid;
    }
}
