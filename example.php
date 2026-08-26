<?php

session_start(); // required so the CSRF token can be stored between requests

require 'vendor/autoload.php';

use Maggsweb\MyFileValidator;
use Maggsweb\MyFormValidator;

// Default field values / no errors - overwritten below on a valid POST
//-----------------------------------------------------------
$fields = [];
$fields['firstname'] = '';
$fields['surname'] = '';
$fields['emailadd'] = '';
$fields['website'] = '';
$fields['password'] = '';
$fields['age'] = '';
$fields['department'] = '';
$fields['interests'] = [];

$errors = false;

// Form Validation
//-----------------------------------------------------------
if (isset($_POST['frmName']) && $_POST['frmName'] == 'example') {

    /**
     * Form Validator
     * ---------------.
     *
     * Flag form as 'POST'
     */
    $formVal = new MyFormValidator('post');

    /*
     * CSRF Token
     * ----------
     * Must be valid before any other submitted data is trusted
     */
    if ($formVal->isValidCsrfToken()) {

        /*
         * Text Input
         * ----------
         * - mandatory
         */
        $formVal->validate('firstname')->clean()->isRequired();

        /*
         * Text Input
         * ----------
         * - optional
         */
        $formVal->validate('surname')->clean();

        /*
         * Email Address
         * -------------
         * - mandatory
         * - validated as email address
         */
        $formVal->validate('emailadd')->clean()->isRequired()->isEmail();

        /*
         * URL
         * ---
         * - mandatory
         * - validated as URL
         */
        $formVal->validate('website')->clean()->isRequired()->isURL();

        /*
         * Number
         * ------
         * - mandatory
         * - validated as numeric, or zero
         * - optionally validating within a set range
         */
        //$formVal->validate('age')->clean()->isRequired()->isNumber();
        $formVal->validate('age')->clean()->isRequired()->isNumber([10, 99]);

        /*
         * Password
         * --------
         * - mandatory
         * - optionally set minimum chars
         * - optionally set maximum chars
         * - optionally require uppercase char
         * (Should only return 1 error at a a time)
         */
        //$formVal->validate('password')->clean()->isRequired()->isPassword();
        $formVal->validate('password')->clean()->isRequired()->isPassword(4, 10, true);

        /*
         * Select Box
         * ----------
         * - mandatory
         */
        $formVal->validate('department')->clean()->isRequired();

        /*
         * Checkbox Group
         * --------------
         * At least $x options should be selected
         */
        //$formVal->validate('interests')->clean()->checkboxGroupRequired();
        $formVal->validate('interests')->clean()->checkboxGroupRequired(2);

        /**
         * Array of error messages.
         * Key   - Form 'name' | span 'id'
         * Value - Validation Message.
         */
        $errors = $formVal->getErrors();

        /**
         * Sanitised form fields for use...
         */
        $fields = $formVal->getFields();

        /**
         * ----------------------------------------------------
         * FILE UPLOAD - FileValidator
         * ----------------------------------------------------.
         */
        $options = [];
        $options['path'] = 'uploads/';
        $options['allow'] = ['txt'];
        $options['deny'] = ['pdf'];
        $options['maxFilesize'] = 1; // 1Mb

        $fileUpload = new MyFileValidator('fileupload');
        $fileUpload->setOptions($options);

        if ($fileUpload->isRequired()->uploadFile()) {
            $fields['fileupload'] = $fileUpload->getSuccess();
        } else {
            $errors['fileupload'] = $fileUpload->getError();
        }
    } else {
        $errors = $formVal->getErrors();
    }

    // ----------------------------------------------------
}

$csrfToken = MyFormValidator::generateCsrfToken();

?>


<pre style="background-color:#d6ffcc;padding: 10px; border: 1px dashed green;">
FORM FIELDS

<?php foreach ($fields as $fieldName => $fieldValue) { ?>
 <?php echo $fieldName . ' = ' . $fieldValue . "\n"; ?>
<?php } ?>
</pre>

<pre style="background-color:#ffaeae;padding: 10px; border: 1px dashed red;">
FORM ERRORS

<?php foreach ($errors as $fieldName => $fieldValue) { ?>
 <?php echo $fieldName . ' = ' . $fieldValue . "\n"; ?>
<?php } ?>
</pre>

<style type='text/css'>
    .error {
        border: 1px solid red;
    }
    .errorMessage {
        color: red
    }
</style>

<!--
Examples are marked up as type='text' so that HTML 5 validation does not modify input validation.
In reality:
    - use type=email, type=number etc..
    - use 'required' for mandatory fields..
for HTML 5 inline validation
-->

<form action="" method="post" enctype="multipart/form-data">

    <table cellspacing="0" cellpadding="3">

        <tr>
            <th><label for="firstname">Name</label> *</th>
            <td><input name="firstname" id='firstname' type="text" value="<?=$fields['firstname'] ?>" /></td>
        </tr>

        <tr>
            <th><label for="surname">Surname</label></th>
            <td><input name="surname" id='surname' type="text" value="<?=$fields['surname'] ?>" /></td>
        </tr>

        <tr>
            <th><label for="emailadd">Email</label> *</th>
            <td><input name="emailadd" id='emailadd' type="text" value="<?=$fields['emailadd'] ?>" /></td>
        </tr>

        <tr>
            <th><label for="website">URL</label> *</th>
            <td><input name="website" id='website' type="text" value="<?=$fields['website'] ?>" /></td>
        </tr>

        <tr>
            <th><label for="password">Password</label> *</th>
            <td><input name="password" id='password' type="password" /></td>
        </tr>

        <tr>
            <th><label for="age">Age</label> *</th>
            <td><input name="age" id='age' type="text" value="<?=$fields['age'] ?>" /></td>
        </tr>

        <tr>
            <th><label for="department">Department</label> *</th>
            <td><select name='department' id='department'>
                    <option value=''>Select..</option>
                    <option value='IT'        <?=isset($fields['department']) && in_array('IT', (array) $fields['department']) ? 'selected' : ''?>>IT</option>
                    <option value='Sales'     <?=isset($fields['department']) && in_array('Sales', (array) $fields['department']) ? 'selected' : ''?>>Sales</option>
                    <option value='Marketing' <?=isset($fields['department']) && in_array('Marketing', (array) $fields['department']) ? 'selected' : ''?>>Marketing</option>
                </select>
            </td>
        </tr>

        <tr>
            <th>Interests *</th>
            <td>
                <span id='interests'><!-- ID for Error -->
                    <input type='checkbox' name='interests[]' value='PHP'   id='cb1' <?=isset($fields['interests']) && in_array('PHP', (array) $fields['interests']) ? 'checked' : ''?> /> <label for='cb1'>PHP</label>
                    <input type='checkbox' name='interests[]' value='MySQL' id='cb2' <?=isset($fields['interests']) && in_array('MySQL', (array) $fields['interests']) ? 'checked' : ''?> /> <label for='cb2'>MySQL</label>
                    <input type='checkbox' name='interests[]' value='OOP'   id='cb3' <?=isset($fields['interests']) && in_array('OOP', (array) $fields['interests']) ? 'checked' : ''?> /> <label for='cb3'>OOP</label>
                </span>
            </td>
        </tr>

        <tr>
            <th>Upload File *</th>
            <td>
                <input type="file" name="fileupload" id="fileupload" />
            </td>
        </tr>

    </table>

    <input name="frmName" type="hidden" value="example"/>
    <input name="csrf_token" id="csrf_token" type="hidden" value="<?=$csrfToken ?>"/>
    <input type="submit" value="Submit"/>

</form>


<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($errors) { ?>
    <?php foreach ($errors as $fieldName => $message) { ?>
    {
        const field = document.getElementById('<?=$fieldName ?>');
        if (field) {
            field.classList.add('error');
            const errorMessage = document.createElement('span');
            errorMessage.className = 'errorMessage';
            errorMessage.textContent = '<?=$message ?>';
            field.insertAdjacentElement('afterend', errorMessage);
        }
    }
    <?php } ?>
    <?php } ?>
});
</script>
