<?php
// CHTML
//
// Constructor Parameters
//  $aIncludeAttributes: array of options to be attached to every element created (ex: readonly => readonly)
//  $sPostArrayName: Post array for element name (ex: name="FieldValues[element-name]")
//
// Common Function Input Parameters:
//  $sLabel: text for the <label> element
//  $sElemID: element id for the id="..." attribute (see note below)
//  $sElemName: element name for the name="..." attribute, matches DB field name
//  $sElemValue: element value for the value="..." attribute
//  $selemAttributes: array of element options (option => value) results in attribute: option="value"
//  $aDataList: array of <input> element data list values
//  $bIsChecked: boolen to select the radio option or check the checkbox option
//  $sSelectValue: string or array of values to select for the <select> element options
//  $aElemValues: array of values for the <select> element options
//
// Element Name Note:
//  The Element Name is prefixed with the $sPostArrayName to post the field as: FieldValues[element-name] (for example)
//  Refer to the CHTMLFormController for details on writing these fields to the database.
//  Special Cases:
//      Multiple[element-name] for checkboxes and multiple <select>
//      Encrypt[element-name] for encrypted fields
//      $_FILES for file upload
//
// Element ID Note:
//  If the element ID is not available as a function input parameter:
//  The element ID is generated from the element name using the createElementID() method.
//  If neccessary to supply an element ID, include the element id in the $aElemAttributes array
//  as: $aElemAttributes['id'] => element-id-value
//
// Javascript Notes:
//  The /src/js/FormFunctions.js performs validation for html elements
//  Some elements include javascript to initialize or format.
//  There is an initForm() javascript method that is called when the form is created.
//
// --------------------------------------------------------

class CHTML
{
    private $bAddUniqueID = true;       // add a unique integer to the element ID (true/false)
    private $sElemUniqueID = '';        // unique element ID generated
    private $aTooltips = array();       // tooltips array indexed by element name
    private $sLabelAttributes = '';     // css class and style attributes for <label> element
    private $sTooltipAttributes = '';   // css class and style attributes for the tooltip element
    private $bIsMSPField = false;       // adds msp_label class to <div> to format label
    private $aIncludeAttributes;        // include attributes on all elements (ex: readonly="readonly")
    private $sPostArrayName;            // Surround element name with post array name
    private static $iTooltipID = 0;
    private $oLog;

    // Flags
    public static $FILE_UPLOAD_ARCHIVE = 'A';
    public static $FILE_UPLOAD_DELETE = 'D';
    public static $FILE_UPLOAD_NONE = '';

    private const DEFAULT_POST_ARRAY = 'FieldValues';

    public function __construct(
        array $aIncludeAttributes=array(), 
        string $sPostArrayName=self::DEFAULT_POST_ARRAY, 
        bool $bDetectChange=true)
    {
        $this->sPostArrayName = $sPostArrayName;
        $this->oLog = new CLog('CHTML');

        // Add onchange event handler to every input element to detect data changes
        // NOTE: tried onload and addlistener but not successfull because Ajax creates this form
        $aDetectChangeEvent = $bDetectChange ? array('onchange' => 'formDetectChange();') : array();
        $this->aIncludeAttributes = array_merge($aIncludeAttributes, $aDetectChangeEvent);
    }

    // Post array name used to create element names
    protected function postArrayName() : string
    {
        return $this->sPostArrayName;
    }

    // Return the most recent unique element ID generated
    public function elementUniqueID() : string
    {
        return $this->sElemUniqueID;
    }

    // Set/Clear the MSP Field flag (adds class to <div> for labels)
    public function isMPSField(bool $bToggle) : void
    {
        $this->bIsMSPField = $bToggle;
    }

    // ----------------------------------------------------
    // <input> elements
    // ----------------------------------------------------

    public function divInputText(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null, array $aDataList=null, string $sPlaceholder=null) : string
    {
        $aElemAttributes = $this->addMinWidthStyleOption($sElemValue, $aElemAttributes);
        return $this->divInput('text', $sLabel, $sElemName, $sElemValue, $aElemAttributes, $aDataList, $sPlaceholder);
    }

    public function divInputNumber(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        if (strlen($sElemValue) == 0) $sElemValue = "0";
        return $this->divInput('number', $sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    // Element Value date format: Y-m-d
    public function divInputDate(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null,$bStartBlank=false) : string
    {
        $sElemValue = (($sElemValue == '') || (str_contains($sElemValue, '0000-00-00'))) ?
            (new DateTime('now'))->format('Y-m-d') :
            (new DateTime($sElemValue))->format('Y-m-d');
        $aElemAttributes = $this->addClassOption('date', $aElemAttributes);    // validate the date format
        $sDefaultDate = $bStartBlank ? "":CValidate::toSQLDate($sElemValue);
        return $this->divInput('date', $sLabel, $sElemName, $sDefaultDate, $aElemAttributes);
    }

    public function divInputDatePrior(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null,$bStartBlank=false) : string
    {
        $sElemValue = (($sElemValue == '') || (str_contains($sElemValue, '0000-00-00'))) ?
            (new DateTime('now'))->format('Y-m-d') :
            (new DateTime($sElemValue))->format('Y-m-d');
        if (is_null($aElemAttributes)) $aElemAttributes = array();
        $aElemAttributes = array_merge($aElemAttributes, array('max' => date('Y-m-d')));
        $aElemAttributes = $this->addClassOption('prior_date', $aElemAttributes);    // validate the date format
        $sDefaultDate = $bStartBlank ? "":CValidate::toSQLDate($sElemValue);
        return $this->divInput('date', $sLabel, $sElemName, $sDefaultDate, $aElemAttributes);
    }

    // Element Value date/time format: Y-m-d h:m:s
    public function divInputDateTime(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        $sElemValue = (($sElemValue == '') || (str_contains($sElemValue, '0000-00-00'))) ?
            (new DateTime('now'))->format('Y-m-d\TH:i') :
            (new DateTime($sElemValue))->format('Y-m-d\TH:i');
        $sElemValue = (new DateTime($sElemValue))->format('Y-m-d\TH:i');
        $aElemAttributes = $this->addClassOption('date', $aElemAttributes);    // validate the date format
        return $this->divInput('datetime-local', $sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    // E-Mail address with validation
    public function divInputEMail(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        $aElemAttributes = $this->addMinWidthStyleOption($sElemValue, $aElemAttributes);
        $aElemAttributes = $this->addClassOption('email', $aElemAttributes);    // validate the email
        return $this->divInput('email', $sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    // Password with validation
    public function divInputPassword(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        if (is_null($aElemAttributes)) $aElemAttributes = array();
        $aElemAttributes = array_merge($aElemAttributes, array('autocomplete' => 'new-password'));  // disable auto-complete
        $aElemAttributes = $this->addClassOption('password', $aElemAttributes);                     // validate the password
        return $this->divInputEncrypted($sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    // Encrypted: post as Encrypt[field-name], decrypt the element value supplied
    // Show/Hide the encrypted value when input gets/loses focus (unless called from password method)
    public function divInputEncrypted(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        $sButton = '';
        if (is_null($aElemAttributes)) $aElemAttributes = array();
        if (! isset($aElemAttributes['autocomplete']))  // not from divInputPassword()
        {
            $aElemAttributes['autocomplete'] = 'off';                           // disable auto-complete
            $aElemAttributes['onfocus'] = "changeEncrypted(this, 'text');";     // view/hide plain text
            $aElemAttributes['onblur'] = "changeEncrypted(this, 'password');";
            if(in_array('readonly',$aElemAttributes)){
                $sButton = "<button type=\"button\" style=\"display:inline-block\" onclick=\"toggleEncrypted(this);\">Reveal</button>";
                $aElemAttributes['width'] = "80%";
                $aElemAttributes['style'] = "display:inline-block";
            }
        }
        $sReset = $this->sPostArrayName;        // Special post array name for the encrypted input
        $this->sPostArrayName = 'Encrypt';
        if (strlen($sElemValue) > 0) $sElemValue = CEncryption::decryptDBValue($sElemValue);        // decrypt the value
        $sHTML = <<<EOT
            {$this->divInput('password', $sLabel, $sElemName, $sElemValue, $aElemAttributes)}
            {$sButton}
            <script>
                function changeEncrypted(elem, changeType)
                {
                    elem.type = changeType;
                }
                function toggleEncrypted(oButton){
                    elem = document.getElementById('{$this->sElemUniqueID}');
                    if(elem.type == 'password'){
                        changeEncrypted(elem,'text');
                        oButton.innerHTML = 'Conceal';
                    }else{
                        changeEncrypted(elem,'password');
                        oButton.innerHTML = 'Reveal';
                    }
                }
            </script>
        EOT;
        $this->sPostArrayName = $sReset;
        return $sHTML;
    }

    // Numeric rate with min, max and step
    public function divInputRate(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        if (strlen($sElemValue) == 0) $sElemValue = "0";
        if (is_null($aElemAttributes)) $aElemAttributes = array();
        $aElemAttributes['prefix'] = "$";
        $aElemAttributes = array_merge($aElemAttributes, array('min'=>'0', 'max'=>'999', 'step'=>'1'));
        return $this->divInput('number', $sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    public function divInputSearch(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null, array $aDataList=null) : string
    {
        return $this->divInput('search', $sLabel, $sElemName, $sElemValue, $aElemAttributes, $aDataList);
    }

    // Phone number with validation
    public function divInputTel(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        $aElemAttributes = $this->addClassOption('phone_number', $aElemAttributes);    // validate the phone number format
        $aElemAttributes['length'] = 12;
        return $this->divInput('tel', $sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    // Element Value time format: h:m:s
    public function divInputTime(
        string $sLabel, string $sElemName, string $sElemValue, array $aElemAttributes=null) : string
    {
        $sElemValue = (new DateTime($sElemValue))->format('H:i');
        $aElemAttributes = $this->addClassOption('time', $aElemAttributes);    // validate the time format
        return $this->divInput('time', $sLabel, $sElemName, $sElemValue, $aElemAttributes);
    }

    // Generic <input>
    public function input(
        string $sInputType,
        string $sElemID,
        string $sElemName,
        string $sElemValue,
        array $aElemAttributes=null,
        array $aDataList=null,
        string $sPlaceholder=null) : string
    {
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;   // unique element ID
        $this->sElemUniqueID = $sElemID;

        $sAttributes = '';
        $sDataList = '';
        if (! is_null($aDataList))
        {
            $sAttributes .= 'list="datalist_' . $sElemID .'" ';
            $sDataList = '<option>' . implode('</option><option>', $aDataList) . '</option>';
            $sDataList = <<<EOT
                <datalist id="datalist_{$sElemID}">{$sDataList}</datalist>
            EOT;
        }

        $sAttributes .= $this->elementAttributes($aElemAttributes);
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span {$sAttributes} style=\"\">&nbsp;".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span {$sAttributes} style=\"\">&nbsp;".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);

        // Attributes, Tooltip and Element Name
        $sElemName = $this->createPostName($sElemName);
        if ($sPlaceholder != null) {
            return <<<EOT
                {$sPrefix}<input type="{$sInputType}" placeholder="{$sPlaceholder}" id="{$sElemID}" name="{$sElemName}" value="{$sElemValue}" {$sAttributes}/>{$sSuffix}
                {$sDataList}
            EOT;
        } else {
            return <<<EOT
                {$sPrefix}<input type="{$sInputType}" id="{$sElemID}" name="{$sElemName}" value="{$sElemValue}" {$sAttributes}/>{$sSuffix}
                {$sDataList}
            EOT;
        }
    }

    // Generic <input> with <div>
    public function divInput(
        string $sInputType,
        string $sLabel,
        string $sElemName,
        string $sElemValue,
        array $aElemAttributes=null,
        array $aDataList=null,
        string $sPlaceholder=null ) : string
    {
        $aDivAttributes = $this->cleanDivAttributes($aElemAttributes);
        if ($this->bIsMSPField) $aDivAttributes = $this->addClassOption('msp_label', $aDivAttributes);
        $sDivAttributes = $this->elementAttributes($aDivAttributes);

        $this->sElemUniqueID = '';      // input() method creates unique ID
        $this->sLabelAttributes = '';   // elemAttributes() method creates label attributes
        $this->sTooltipAttributes = ''; // elemAttributes() method creates tooltip attributes
        $sElemID = $this->createElementID($sElemName, $aElemAttributes);
        $sTooltip = $this->getTooltip($sElemName);
        $element = $this->input($sInputType, $sElemID, $sElemName, $sElemValue, $aElemAttributes, $aDataList, $sPlaceholder);
        $sShowLabel = strlen($sLabel) > 0 ? "<label for=\"{$this->sElemUniqueID}\"{$this->sLabelAttributes}>{$sLabel}:{$sTooltip}</label>" : '';
        return <<<EOT
            <div{$sDivAttributes}>
                {$sShowLabel}
                {$element}
            </div>
        EOT;
    }

    // SPECIAL CASE: input type="file" for ADA compliance
    // <input type="file"> element is hidden
    // Include hidden field for "archive" or "delete" existing file
    // Remove 'required' class if file id value provided, change read-only to disabled
    public function divInputFile(
        string $sLabel,             // a blank label will exclude the <label> element!
        string $sElemName, 
        string $sFileID='-1',       // file ID expected
        array $aElemAttributes=null, 
        string $sPreviousFileFlag='',
        string $sArchieveDescription='') : string
    {
        static $iUnique = 0;
        $sID = strval(++$iUnique);
        $iFileID = CValidate::validInt($sFileID);
        $bFileExists = (($sFileID != '') && ($iFileID > -1));
        $aElemAttributes = array_merge((is_null($aElemAttributes) ? array() : $aElemAttributes), $this->aIncludeAttributes);

        // Separate the <div> attributes and the <input type="file"...> attributes
        // class attribute may have conditional show/hide values for the <div>
        // Add OnChange event to set the selected file name
        $sWrapperClass = '';
        $bAllowMultiple = false;
        $aFileAttributes = array(
            'onchange' => "formSelectFile('{$sID}')",
            'accept' => '.pdf,.jpg,.png');              // default
        if ($bFileExists) $aElemAttributes = $this->removeClassOption('required', $aElemAttributes);
        if (str_contains($aElemAttributes['class'], 'required'))    // apply class to both <div> and <input>
        {
            $aFileAttributes['class'] = $aElemAttributes['class'];
        }
        if (isset($aElemAttributes['readonly']))    // change read-only to disabled
        {
            $aFileAttributes['disabled'] = 'disabled';
            unset($aElemAttributes['readonly']);
            $sWrapperClass = ' read_only';
        }
        if (isset($aElemAttributes['multiple']))      // accept multiple files dropped
        {
            $bAllowMultiple = true;
            $aFileAttributes['multiple'] = $aElemAttributes['multiple'];
            unset($aElemAttributes['multiple']);
        }
        if (isset($aElemAttributes['accept']))      // accept file types
        {
            $aFileAttributes['accept'] = $aElemAttributes['accept'];
            unset($aElemAttributes['accept']);
        }

        $sPrefix = isset($aElemAttributes['prefix']) ? $aElemAttributes['prefix'] . '&nbsp;': '';
        $sSuffix = isset($aElemAttributes['suffix']) ? '&nbsp;' . $aElemAttributes['suffix'] : '';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);

        // Remaining <div> attributes and <input type="files"...> attributes
        $sDivAttributes = $this->elementAttributes($this->cleanDivAttributes($aElemAttributes));
        $sFileAttributes = $this->elementAttributes($aFileAttributes);

        // File upload element and hidden "delete" element or "archive" element for previous file
        // Javascript handlers defined above
        $sLink = '';
        $sHidden = '';
        $sViewButtons = '';
        if ($bFileExists)
        {
            $sDisplayFileURL = CFileManager::getDisplayURL($iFileID);
            $sOnClick = CHTMLForm::onClickHandlerNewWindow($sDisplayFileURL);
            $sViewButtons .= '<button type="button" style="margin: 0 0 0 5px;" onclick="' . $sOnClick . '">View</button>';
            $sLink = '<span class="download_file">' . CFileManager::getFileLink($iFileID, 'Download') . '</span>';
            if ($sPreviousFileFlag == self::$FILE_UPLOAD_DELETE)
            {
                $sHidden = $this->hidden("DeleteFile[{$sElemName}]", $sFileID, false);
            }
            elseif ($sPreviousFileFlag == self::$FILE_UPLOAD_ARCHIVE)
            {
                $sHidden = $this->hidden("ArchiveFile[{$sElemName}]", $sFileID, false);
                if(strlen($sArchieveDescription)>0) $sHidden .= $this->hidden("ArchiveDesc[{$sElemName}]", $sArchieveDescription, false);
            }
            else
            {
                $sHidden = $this->hidden("PriorFile[{$sElemName}]", $sFileID, false);
            }
        }

        $sTooltip = $this->getTooltip($sElemName);
        $sViewDocuments = $sViewButtons != '' ? "<span style=\"margin:0;\">View File: {$sViewButtons}</span>" : '';
        $sLabelElem = strlen($sLabel) > 0 ? "<label>{$sLabel}:{$sLink}{$sTooltip}</label>" : '';
        if ($bAllowMultiple) $sElemName .= '[]';
        $sPlaceholder = $bAllowMultiple ? 'Drag files here' : 'Drag 1 file here';
        // REPLACED w/ button: <span role="button" aria-controls="filename_{$sID}">Choose File</span>
        return <<<EOT
            <div {$sDivAttributes}>
                {$sViewDocuments}
                {$sLabelElem}
                <div class="file_upload_wrapper{$sWrapperClass}">
                    <!-- hidden file upload element -->
                    <input type="file" id="file_{$sID}" name="{$sElemName}" {$sFileAttributes} />
                    <!-- label triggers file upload element, for file selection popup -->
                    {$sPrefix}
                    <label for="file_{$sID}" id="label_{$sID}">
                        <button type="button" class="upload_button" aria-controls="filename_{$sID}" onclick="document.getElementById('file_{$sID}').click();">Choose File</button>
                    </label>
                    <!-- display selected file name -->
                    <label for="filename_{$sID}" class="hide">Uploaded File</label>
                    <input type="text" id="filename_{$sID}" ondrop="formDropFile(event, '{$sID}');" ondragover="return false;" autocomplete="off" readonly="readonly" placeholder="{$sPlaceholder}" />
                    {$sSuffix}
                    {$sTooltip}
                </div>
                {$sHidden}
            </div>
        EOT;
    }

    // ----------------------------------------------------
    // <input> radio buttons and checkboxes
    // ----------------------------------------------------

    // Radio <input>
    public function inputRadio(
        string $sLabel,
        string $sElemID,
        string $sElemName,
        string $sElemValue,
        bool $bIsChecked = false,
        array $aElemAttributes=null) : string

    {
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;

        // Element Name, Attributes and checked/unchecked
        $sElemName = $this->createPostName($sElemName);
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sChecked = $bIsChecked ? 'checked="checked"' : '';
        return <<<EOT
            <input type="radio" id="{$sElemID}" name="{$sElemName}" value="{$sElemValue}" {$sChecked} {$sAttributes}/>
                <label for="{$sElemID}">{$sLabel}</label>
        EOT;
    }

    // Radio <div> with array of radio <input>s
    public function divRadio(
        string $sLabel,
        array $aRadioInputs,
        array $aElemAttributes = array(),
        string $sTooltip = '') : string
    {
        $sAttributes = $this->elementAttributes($this->addClassOption('radio_div', $aElemAttributes));
        $sRadioInputs = implode('', $aRadioInputs);
        if (strlen($sLabel) > 0) $sLabel .= ':';
        return <<<EOT
            <div {$sAttributes}>
                <fieldset><legend style="display: contents;"><label>{$sLabel}{$sTooltip}</label></legend>
                <div>
                    {$sRadioInputs}
                </div>
                </fieldset>
            </div>
        EOT;
    }

    // Checkbox <input>
    // Special Case: posts array of Multiple[field-name][] values
    // A hidden field ensures that checkbox field will be cleared if no are checkboxes selected
    // Selections are stored in a DB field as a comma-separated string
    public function inputCheckbox(
        string $sLabel,
        string $sElemID,
        string $sElemName,
        string $sElemValue,
        bool $bIsChecked = false,
        array $aElemAttributes = null) : string
    {
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;

        // Element Name, Attributes and checked/unchecked
        $sPostElemName = $this->createPostName($sElemName . '[]');
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sChecked = $bIsChecked ? 'checked="checked"' : '';

        // One hidden field for this field name with blank value to ensure field is cleared if no selection
        $sClearCheckbox = '';
        static $aClearCheckbox = array();
        if (! isset($aClearCheckbox[$sElemName]))
        {
            $aClearCheckbox[$sElemName] = true;
            $sClearCheckbox = $this->hidden($sElemName . '[]', '');
        }
        $iWidth = strlen($sLabel) + 4;  // ch width
        return <<<EOT
            {$sClearCheckbox}
            <input type="checkbox" id="{$sElemID}" name="{$sPostElemName}" value="{$sElemValue}" {$sChecked} {$sAttributes}/>
                <label for="{$sElemID}" style="min-width:{$iWidth}ch;">{$sLabel}</label>
        EOT;
    }

    // Checkbox <div> with array of checkbox <input>s
    public function divCheckbox(
        string $sLabel,
        array $aCheckboxInputs,
        array $aElemAttributes = array(),
        string $sTooltip = '') : string
    {
        $sAttributes = $this->elementAttributes($this->addClassOption('checkbox_div', $aElemAttributes));
        $sCheckboxInputs = implode('', $aCheckboxInputs);
        if (strlen($sLabel) > 0) $sLabel .= ':';
        return <<<EOT
            <div {$sAttributes}>
                <fieldset><legend style="display: contents;"><label>{$sLabel}{$sTooltip}</label></legend>
                <div>
                    {$sCheckboxInputs}
                </div>
            </div>
        EOT;
    }

    // ----------------------------------------------------
    // <select> elements
    // ----------------------------------------------------

    // <select> element
    public function select(
        string $sElemID,
        string $sElemName,
        $sSelectValue,      /* string or array */
        array $aElemValues,
        array $aElemAttributes=null) : string
    {
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;   // unique element ID
        $this->sElemUniqueID = $sElemID;
        
        // Multiple <select>: explode the selected values into an array
        $bIsMultiple = isset($aElemAttributes['multiple']);
        if ($bIsMultiple && (! is_array($sSelectValue))) $sSelectValue = explode(',', $sSelectValue);

        // <select> Options
        $sValues = '';
        foreach($aElemValues as $sValue => $sPrompt)
        {
            $sSelected = (is_array($sSelectValue)) ?
                (in_array($sValue, $sSelectValue) ? ' selected="selected"' : '') :
                (strval($sValue) == $sSelectValue ? ' selected="selected"' : '');
            $sValues .= "<option value=\"{$sValue}\"{$sSelected}>{$sPrompt}</option>";
        }

        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);

        // Attributes, Tooltip and Element Name
        $sAttributes = $this->elementAttributes($aElemAttributes);
        if ($bIsMultiple) $sElemName .= '[]';
        $sElemName = $this->createPostName($sElemName);
        return <<<EOT
            {$sPrefix}<select id="{$sElemID}" name="{$sElemName}" {$sAttributes}>{$sValues}</select>{$sSuffix}
        EOT;
    }

    // <select> element with options from DB rows
    public function selectDBRows(
        string $sElemID,
        string $sElemName,
        array $aRows,
        string $sKeyFieldName,
        string $sValueFieldName,
        $sSelectValue,              /* string or array */
        array $aElemAttributes=null) : string
    {
        $aElemValues = array();
        foreach($aRows as $aFields)
        {
            $aElemValues[$aFields[$sKeyFieldName]] = $aFields[$sValueFieldName];
        }
        return $this->select($sElemID, $sElemName, $sSelectValue, $aElemValues, $aElemAttributes);
    }

    // <select> element with options from DB rows
    public function divSelectDBRows(
        string $sLabel,
        string $sElemName,
        array $aRows,
        string $sKeyFieldName,
        string $sValueFieldName,
        $sSelectValue,              /* string or array */
        array $aElemAttributes=null,
        $aElemValues = array()) : string
    {
        foreach($aRows as $aFields)
        {
            $aElemValues[$aFields[$sKeyFieldName]] = $aFields[$sValueFieldName];
        }
        return $this->divSelect($sLabel, $sElemName, $sSelectValue, $aElemValues, $aElemAttributes);
    }

    // <select> element with <div>
    public function divSelect(
        string $sLabel,
        string $sElemName,
        $sSelectValue,              /* string or array */
        array $aElemValues,
        array $aElemAttributes=null) : string
    {
        $aDivAttributes = $this->cleanDivAttributes($aElemAttributes);
        if ($this->bIsMSPField) $aDivAttributes = $this->addClassOption('msp_label', $aDivAttributes);
        $sDivAttributes = $this->elementAttributes($aDivAttributes);

        $this->sElemUniqueID = '';      // select() method creates unique ID
        $this->sLabelAttributes = '';   // elemAttributes() method creates label attributes
        $this->sTooltipAttributes = ''; // elemAttributes() method creates tooltip attributes
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);
        $sElemID = $this->createElementID($sElemName, $aElemAttributes);
        $element = $this->select($sElemID, $sElemName, $sSelectValue, $aElemValues, $aElemAttributes);
        
        $sTooltip = $this->getTooltip($sElemName);
        $sClearSelection = isset($aElemAttributes['multiple']) ? $this->hidden($sElemName . '[]', '') : '';
        if (strlen($sLabel) > 0) $sLabel .= ':';
        return <<<EOT
            <div{$sDivAttributes}>
                {$sClearSelection}
                <label for="{$this->sElemUniqueID}"{$this->sLabelAttributes}>{$sLabel}{$sTooltip}</label>
                {$sPrefix}{$element}{$sSuffix}
            </div>
        EOT;
    }

    // User Accounts <select> element with <div>
    // Call the appropriate COptions method first to obtain the options array
    // COptions method will also set the selected user account fields for the hidden <div>
    public function divSelectUserAccount(
        string $sLabel,
        string $sElemName,
        string $sSelectValue,
        array $aElemValues,
        array $aElemAttributes=null) : string
    {
        // Contact information for the selected user account record, show in hidden <div>
        // Selected User Account fields retained by COptions method
        $sContactDiv = '';
        $sBtn = '';
        $aFields = COptions::selectUserAccountFields();
        if (count($aFields) > 0)
        {
            $oPermissionDefinition = new CPermissionsDefinition();
            $sPrimaryRole = $oPermissionDefinition->roleName(CValidate::validInt($aFields['PrimaryRole']));
            $sSecondaryRole = ($aFields['SecondaryRole'] > '-1') ?
                "<br />Secondary Role: {$oPermissionDefinition->roleName(CValidate::validInt($aFields['SecondaryRole']))}" : '';
            $sAdditionalPhone = $aFields['AdditionalPhoneDesc'] > -1 ?
                "<br />{$aFields['AdditionalPhoneDesc']}: {$aFields['AdditionalPhoneNumber']} {$aFields['AdditionalPhoneExt']}" : '';
            $sContents = <<<EOT
                Title: {$aFields['Title']}
                <br />Primary Role: {$sPrimaryRole}
                {$sSecondaryRole}
                <br />{$aFields['PrimaryPhoneDesc']}: {$aFields['PrimaryPhoneNumber']} {$aFields['PrimaryPhoneExt']}
                {$sAdditionalPhone}
                <br />{$aFields['EMailAddress']}
            EOT;
            // $sContactDiv = $this->getToggleDiv($sContents, $sBtn);
        }

        $sDiv = $this->divSelect($sLabel, $sElemName, $sSelectValue, $aElemValues, $aElemAttributes);
        return $sDiv . $sBtn . $sContactDiv;
    }

    // ----------------------------------------------------
    // <header> element
    // ----------------------------------------------------

    // <header> element
    public function header(
        string $sElemID,
        string $sElemValue,
        array $aElemAttributes=null) : string
    {
        $sTagName = $sElemID;
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;
        $this->sElemUniqueID = $sElemID;

         // Element Name, Attributes and checked/unchecked
         $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
         $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
         unset($aElemAttributes['prefix']);
         unset($aElemAttributes['suffix']);
 
         // Attributes, Tooltip and Element Name
         //$aElemAttributes = $this->addStyleOption('width:60%;', $aElemAttributes); // SET WIDTH IN ATTRIBUTES INPUT
         $sAttributes = $this->elementAttributes($aElemAttributes);
         return <<<EOT
         {$sPrefix}<{$sTagName} id="{$sElemID}"  {$sAttributes}>{$sElemValue}</{$sTagName}>{$sSuffix}
        EOT;
    }

    // ----------------------------------------------------
    // <textarea> element
    // ----------------------------------------------------

    // <textarea> element
    public function textArea(
        string $sElemID,
        string $sElemName,
        string $sElemValue,
        array $aElemAttributes=null,
        string $sPlaceholder=null) : string
    {
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;
        $this->sElemUniqueID = $sElemID;

        // Element Name, Attributes and checked/unchecked
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);

        // Attributes, Tooltip and Element Name
        //$aElemAttributes = $this->addStyleOption('width:60%;', $aElemAttributes); // SET WIDTH IN ATTRIBUTES INPUT
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sElemName = $this->createPostName($sElemName);
        if ($sPlaceholder != null) {
            return <<<EOT
                {$sPrefix}<textarea id="{$sElemID}" placeholder="{$sPlaceholder}" name="{$sElemName}" {$sAttributes}>{$sElemValue}</textarea>{$sSuffix}
            EOT;
        } else {
            return <<<EOT
                {$sPrefix}<textarea id="{$sElemID}" name="{$sElemName}" {$sAttributes}>{$sElemValue}</textarea>{$sSuffix}
            EOT;
        }
    }

    // <textarea> element
    public function textDisplay(
        string $sElemID,
        string $sElemLabel,
        string $sElemValue,
        array $aElemAttributes=null) : string
    {
        static $iUnique = 0;
        if ($this->bAddUniqueID) $sElemID .= '_' . ++$iUnique;
        $this->sElemUniqueID = $sElemID;

        // Element Name, Attributes and checked/unchecked
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);

        // Attributes, Tooltip and Element Name
        //$aElemAttributes = $this->addStyleOption('width:60%;', $aElemAttributes); // SET WIDTH IN ATTRIBUTES INPUT
        $sAttributes = $this->elementAttributes($aElemAttributes);
        if(strlen($sElemValue) == 0){
            return "";
        }
        return <<<EOT
            {$sPrefix}<div id="{$sElemID}" name="Text" {$sAttributes}><label style="font-weight:bold">{$sElemLabel}:</label>{$sElemValue}</div>{$sSuffix}
        EOT;
    }
    
    // <textarea> element with <div>
    public function divTextArea(
        string $sLabel,
        string $sElemName,
        string $sElemValue,
        array $aElemAttributes=null,
        string $sPlaceholder=null) : string
    {
        $aDivAttributes = $this->cleanDivAttributes($aElemAttributes);
        if ($this->bIsMSPField) $aDivAttributes = $this->addClassOption('msp_label', $aDivAttributes);
        $sDivAttributes = $this->elementAttributes($aDivAttributes);
        $sClass = isset($aElemAttributes['class']) ? $aElemAttributes['class'] : '';

        $this->sElemUniqueID = '';      // textArea() method creates unique ID
        $this->sLabelAttributes = '';   // elemAttributes() method creates label attributes
        $this->sTooltipAttributes = ''; // elemAttributes() method creates tooltip attributes

        $sPrefix = isset($aElemAttributes['prefix']) ? "<span {$sClass} style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span {$sClass} style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);
        $sElemID = $this->createElementID($sElemName, $aElemAttributes);
        $element = $this->textArea($sElemID, $sElemName, $sElemValue, $aElemAttributes, $sPlaceholder);
        $sTooltip = $this->getTooltip($sElemName);
        if (strlen($sLabel) > 0) $sLabel .= ':';
        return <<<EOT
            <div{$sDivAttributes}>
                <label for="{$this->sElemUniqueID}"{$this->sLabelAttributes}>{$sLabel}{$sTooltip}</label>
                {$sPrefix}{$element}{$sSuffix}
            </div>
        EOT;
    }
    // <textarea> element with <div>
    public function divTextAreaNote(
        string $sLabel,
        string $sElemName,
        string $sElemValue,
        array $aElemAttributes=null,
        string $sFormID = 'generic_form_1') : string
    {
        $aDivAttributes = $this->cleanDivAttributes($aElemAttributes);
        if ($this->bIsMSPField) $aDivAttributes = $this->addClassOption('msp_label', $aDivAttributes);
        $sDivAttributes = $this->elementAttributes($aDivAttributes);

        $this->sElemUniqueID = '';      // textArea() method creates unique ID
        $this->sLabelAttributes = '';   // elemAttributes() method creates label attributes
        $this->sTooltipAttributes = ''; // elemAttributes() method creates tooltip attributes
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);
        unset($aElemAttributes['disabled']);
        unset($aElemAttributes['readonly']);
        $sElemID = $this->createElementID($sElemName, $aElemAttributes);
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sTooltip = $this->getTooltip($sElemName);
        $sElemName = $this->createPostName($sElemName);
        
        if (strlen($sLabel) > 0) $sLabel .= ':';
        return <<<EOT
            <div{$sDivAttributes}>
                <label for="{$this->sElemUniqueID}"{$this->sLabelAttributes} style="float:left;width: 30px;">{$sLabel}{$sTooltip}</label>
                {$sPrefix}<textarea id="{$sElemID}" name="{$sElemName}" {$sAttributes} onBlur="autoUpdate('{$sFormID}');" >{$sElemValue}</textarea>{$sSuffix}
            </div>
        EOT;
    }

    // ----------------------------------------------------
    // <div> with text message
    // ----------------------------------------------------

    public function divMessage(string $sLabel, string $sMsg, string $sToolTip='') : string
    {
        return <<<EOT
            <div>
                <label>{$sLabel}:</label>{$sToolTip}
                <p style="display:inline-block;padding-top:7px;">{$sMsg}</p>
            </div>
        EOT;
    }

    public function divMessageTight(string $sLabel, string $sMsg) : string
    {
        return <<<EOT
            <div>
                <label">{$sLabel}:</label>
                <p style="display:inline-block;padding-top:2px;white-space: pre-line;">{$sMsg}</p>
            </div>
        EOT;
    }

    public function inlineMessage(string $sLabel, string $sMsg) : string
    {
        return <<<EOT
            <label style="display:inline-block;margin:0 2px 0 0;padding:0">{$sLabel}:</label>
            {$sMsg}<br />
        EOT;
    }

    // ----------------------------------------------------
    // Hidden Fields
    // ----------------------------------------------------

    // Create the hidden <input> element
    public function hidden(string $sElemName, string $sElemValue, $bUsePostArray=true)
    {
        static $iUnique = 0;

        $sElemID = $this->createElementID($sElemName) . '_' . ++$iUnique;
        $sElemName = $bUsePostArray ? $this->createPostName($sElemName) : $sElemName;
        return <<<EOT
            <input type="hidden" id="{$sElemID}" name="{$sElemName}" value="{$sElemValue}" />
        EOT;
    }

    // ----------------------------------------------------
    // <ul> with list items
    // ----------------------------------------------------

    // Return the <ul> list of items from the array
    public function ul(array $aListItems, array $aElemAttributes=null) : string
    {
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);
        $sAttributes = $this->elementAttributes($aElemAttributes);
        return "<ul {$sAttributes}><li>" . $sPrefix . implode($sSuffix . '</li><li>' . $sPrefix, $aListItems) . $sSuffix . '</li></ul>';
    }

    // ----------------------------------------------------
    // Complex fields (multiple)
    // ----------------------------------------------------

    // Name <div>
    // $aFieldNameValues array (2 or 3 elements in order):
    //  $aFieldNameValues[first-name-field-name] = first-name
    //  $aFieldNameValues[middle-name-field-name] = middle-name (optional)
    //  $aFieldNameValues[last-name-field-name] = last-name
    public function divName(array $aFieldNameValues, array $aElemAttributes=null) : string
    {
        if ((count($aFieldNameValues) != 2) && (count($aFieldNameValues) != 3))  // sanity check
        {
            $this->oLog->debug('CHTML: invalid field values array for divName() method');
            return '';
        }

        static $iUnique = 0;
        ++$iUnique;
        if (is_null($aElemAttributes)) $aElemAttributes = array();

        $aValues = array();
        $aPostNames = array();          // example: FieldValues[FirstName]
        foreach(array_keys($aFieldNameValues) as $index => $sFieldName) // array keys are the field names
        {
            $aPostNames[$index] = $sFieldName;
            $aValues[$index] = $aFieldNameValues[$sFieldName];
        }

        $this->sLabelAttributes = '';   // elemAttributes() method creates label attributes
        $this->sTooltipAttributes = ''; // elemAttributes() method creates tooltip attributes
        $sIgnore = $this->elementAttributes($aElemAttributes); // check for required class for <label>
        if (count($aFieldNameValues) == 2)
        {
            $sIDFirst = 'first_name_' . $iUnique;
            $sIDLast = 'last_name_' . $iUnique;
            return <<<EOT
                <div class="user_name">
                    <label for="first_name_{$iUnique}"{$this->sLabelAttributes}>First, Last Name:</label>
                    {$this->input('text', $sIDFirst, $aPostNames[0], $aValues[0], $aElemAttributes, array('placeholder'=>'First Name', 'aria-label' => 'First Name'))}
                    {$this->input('text', $sIDLast, $aPostNames[1], $aValues[1], $aElemAttributes, array('placeholder'=>'Last Name', 'aria-label' => 'Last Name'))}
                </div>
            EOT;
        }
        elseif (count($aFieldNameValues) == 3)
        {
            $sIDFirst = 'first_name_' . $iUnique;
            $sIDMiddle = 'middle_name_' . $iUnique;
            $sIDLast = 'last_name_' . $iUnique;
            $aMiddleNameAttributes = $aElemAttributes;
            if (isset($aMiddleNameAttributes['class']) && ($aMiddleNameAttributes['class'] == 'required'))
            {
                unset($aMiddleNameAttributes['class']); // never required
            }
            return <<<EOT
                <div class="user_name">
                    <label for="first_name_{$iUnique}"{$this->sLabelAttributes}>First, Middle, Last Name:</label>
                    {$this->input('text', $sIDFirst, $aPostNames[0], $aValues[0], array_merge($aElemAttributes, array('placeholder'=>'First Name', 'aria-label' => 'First Name')))}
                    {$this->input('text', $sIDMiddle, $aPostNames[1], $aValues[1], array_merge($aMiddleNameAttributes, array('placeholder'=>'Middle Name', 'aria-label' => 'Middle Name')))}
                    {$this->input('text', $sIDLast, $aPostNames[2], $aValues[2], array_merge($aElemAttributes, array('placeholder'=>'Last Name', 'aria-label' => 'Last Name')))}
                </div>
            EOT;
        }
    }

    // Address <div>s
    // $sLabel for (optional) name field
    // $aFieldNameValues array (up to 6 elements in order):
    //  $aFieldNameValues[name] = hospital, school, business name
    //  $aFieldNameValues[address1-field-name] = address1
    //  $aFieldNameValues[address2-field-name] = address2
    //  $aFieldNameValues[city-field-name] = city
    //  $aFieldNameValues[state-field-name] = state
    //  $aFieldNameValues[zip-code-field-name] = zip-code
    public function divAddress(string $sLabel, array $aFieldNameValues, $aElemAttributes=null, $sGooglePlacesType='') : string
    {
        // Inputs vary based on Google Places type
        if (is_null($aElemAttributes)) $aElemAttributes = array();
        $nFields = count($aFieldNameValues);
        switch($sGooglePlacesType)
        {
            case 'hospital':        // 6 inputs
            case 'establishment':
                if (($nFields != 4) && ($nFields != 6)) throw new \Exception('invalid number of params'); {
                    if($nFields == 4) {
                        $indexName = -1;    // not used
                        $indexAddress1 = 0;
                        $indexAddress2 = -1;    // not used
                        $indexCity = 1;
                        $indexState = 2;
                        $indexZipCode = 3;
                        break;
                    } else {
                        $indexName = 0; //Should be 0
                        $indexAddress1 = 1;
                        $indexAddress2 = 2;
                        $indexCity = 3;
                        $indexState = 4;
                        $indexZipCode = 5;
                        break;
                    }
                }
            case 'school':          // 4 inputs
                if ($nFields != 4) throw new \Exception('invalid number of params');
                $indexName = 0;
                $indexAddress1 = -1;    // not used
                $indexAddress2 = -1;    // not used
                $indexCity = 1;
                $indexState = 2;
                $indexZipCode = 3;
                break;
            case 'address':  // 5 inputs
            default:
                if (($nFields != 4) && ($nFields != 5)) throw new \Exception('invalid number of params');
                $i = 0;
                $indexName = -1;        // not used
                $indexAddress1 = $i;
                $indexAddress2 = ($nFields == 5) ? ++$i : -1;
                $indexCity = ++$i;
                $indexState = ++$i;
                $indexZipCode = ++$i;
                break;
        }

        static $iUnique = 0;
        ++$iUnique;

        // Special case: location required (city, state & zip)
        if (isset($aElemAttributes['location']) && str_contains($aElemAttributes['location'], 'required'))
        {
            unset($aElemAttributes['location']);
            $aLocationAttributes = $this->addClassOption('required', $aElemAttributes);
        }
        else
        {
            $aLocationAttributes = $aElemAttributes;
        }

        // Google Places script for address lookup
        // Passes $sNewPlacesVar, $sGooglePlacesType, $aLatLng[0], $aLatLng[1], and $sGoogleInputClass to google_places.js
        // $sGoogleInputClass adds CSS class to first input of google places address lookup array
        $sGooglePlacesScript = '';
        $sTimeVar = time();
        $sGoogleInputClass = 'googleInput';
        if ($sGooglePlacesType != '')
        {
            $sNewPlacesVar = 'places_'.$iUnique;
            $aElemAttributes = $this->addClassOption($sNewPlacesVar, $aElemAttributes);
            $aLocationAttributes = $this->addClassOption($sNewPlacesVar, $aLocationAttributes);
            $aLatLng = CUserCurrent::userLocation();
            $sGooglePlacesScript = <<<EOT
                <script src="/application/src/js/google_places.js?v={$sTimeVar}"></script>
                <script>
                    assignFields("{$sNewPlacesVar}", "{$sGooglePlacesType}", "{$aLatLng[0]}", "{$aLatLng[1]}", "{$sGoogleInputClass}");
                </script>
            EOT;
        }

        $aValues = array();
        $aPostNames = array();          // example: FieldValues[Address1]
        foreach(array_keys($aFieldNameValues) as $index => $sFieldName) // array keys are the field names
        {
            $aPostNames[$index] = $sFieldName;
            $aValues[$index] = $aFieldNameValues[$sFieldName];
        }

        // State options: assign current user's state if state is not initialized
        $aStateOptions = COptions::state(array('-1'=>'-- State --'));
        if (($aValues[$indexState] == '') || ($aValues[$indexState] == '-1')) $aValues[$indexState] = CUserCurrent::userAccountField('State');

        // Unique ID for each address element (pass via the options array)
        $sNameField = '';
        $sAddress1 = '';
        $sAddress2 = '';
        if ($indexName > -1)
        {
            $sNameField = $this->divInputText($sLabel, $aPostNames[$indexName], $aValues[$indexName], $aElemAttributes);
        }

        $aAddress1Attributes = $aElemAttributes;
        if ($indexAddress1 > -1)
        {
            $aAddress1Attributes['id'] = 'address1_' . $iUnique;
            $sAddress1 = $this->divInputText('Address', $aPostNames[$indexAddress1], $aValues[$indexAddress1], array_merge($aAddress1Attributes, array('placeholder' => 'Address 1', 'aria-label' => 'Address 1')));
            if ($indexAddress2 > -1)
            {
                $aAddress2Attributes = $this->removeClassOption('required', $aElemAttributes);
                $aAddress2Attributes['id'] = 'address2_' . $iUnique;
                $sAddress2 = $this->divInputText('', $aPostNames[$indexAddress2], $aValues[$indexAddress2], array_merge($aAddress2Attributes, array('placeholder' => 'Address 2', 'aria-label' => 'Address 2')));
            }
        }

        // Unique ID for city, state, zip code
        $sIDCity = 'city_' . $iUnique;
        $sIDState = 'state_'. $iUnique;
        $sIDZip = 'zip_code_' . $iUnique;
        $aCityAttributes = $this->addMinWidthStyleOption($aValues[$indexCity], $aLocationAttributes);
        $sLabelClass = isset($aLocationAttributes['class']) && (str_contains($aLocationAttributes['class'], 'required')) ? 
            ' class="label_required"' : '';

        return <<<EOT
            <div>
                {$sNameField}
            </div>
            <div class="user_address">
                {$sAddress1}
                {$sAddress2}
            </div>
            <div class="user_city_state_zip">
                <label{$sLabelClass}>City, State, Zip Code:</label>
                {$this->input('text', $sIDCity, $aPostNames[$indexCity], $aValues[$indexCity], array_merge($aCityAttributes, array('placeholder' => 'City', 'aria-label' => 'City')))}
                {$this->select($sIDState, $aPostNames[$indexState], $aValues[$indexState], $aStateOptions, array_merge($aLocationAttributes, array('aria-label' => 'State')))}
                {$this->input('text', $sIDZip, $aPostNames[$indexZipCode], $aValues[$indexZipCode], array_merge($aLocationAttributes, array('placeholder' => 'Zip Code', 'aria-label' => 'Zip Code')))}
            </div>
            {$sGooglePlacesScript}
        EOT;
    }

    // Address <div>s
    // $aFieldNameValues array (3 elements in order):
    //  $aFieldNameValues[city-field-name] = city
    //  $aFieldNameValues[state-field-name] = state
    //  $aFieldNameValues[zip-code-field-name] = zip-code (OPTIONAL)
    public function divCityStateZip(array $aFieldNameValues, $aElemAttributes=null) : string
    {
        if ((count($aFieldNameValues) != 2) && (count($aFieldNameValues) != 3))  // sanity check
        {
            $this->oLog->debug('CHTML: invalid field values array for divCityStateZip() method');
            return '';
        }

        static $iUnique = 0;
        ++$iUnique;
        $aStateOptions = COptions::state(array('-1'=>'-- State --'));

        $aValues = array();
        $aPostNames = array();          // example: FieldValues[City]
        foreach(array_keys($aFieldNameValues) as $index => $sFieldName) // array keys are the field names
        {
            $aPostNames[$index] = $sFieldName;
            $aValues[$index] = $aFieldNameValues[$sFieldName];
        }
        // Default state to current user's state
        if (($aValues[1] == '') || ($aValues[1] == '-1')) $aValues[1] = CUserCurrent::userAccountField('State');

        // Unique ID for each address element (pass via the options array)
        $aAddress1Attributes = is_null($aElemAttributes) ? array() : $aElemAttributes;
        $aAddress1Attributes['id'] = 'citystatezip_' . $iUnique;
        $aCityAttributes = $this->addMinWidthStyleOption($aValues[0], $aAddress1Attributes);

        // Unique ID for city, state, zip code
        $sIDCity = 'city_' . $iUnique;
        $sIDState = 'state_'. $iUnique;
        if (count($aFieldNameValues) == 3)
        {
            $sIDZip = 'zip_code_' . $iUnique;
            $sZipPrompt = ', Zip Code';
            $sZipCodeElements = $this->input('text', $sIDZip, $aPostNames[2], $aValues[2], array_merge($aAddress1Attributes, array('placeholder' => 'Zip Code', 'aria-label' => 'Zip Code')));
        }
        else
        {
            $sZipPrompt = '';
            $sZipCodeElements = '';
        }

        return <<<EOT
            <div class="user_city_state_zip">
                <label{$this->sLabelAttributes}>City, State{$sZipPrompt}:</label>
                {$this->input('text', $sIDCity, $aPostNames[0], $aValues[0], array_merge($aCityAttributes, array('placeholder' => 'City', 'aria-label' => 'City')))}
                {$this->select($sIDState, $aPostNames[1], $aValues[1], $aStateOptions, array_merge($aAddress1Attributes, array('aria-label' => 'State')))}
                {$sZipCodeElements}
            </div>
        EOT;
    }

    // Phone <div>
    // $aFieldNameValues array (3 elements in order):
    //  $aFieldNameValues[description-field-name] = description selected
    //  $aFieldNameValues[phone-number-field-name] = phone-number
    //  $aFieldNameValues[phone-ext-field-name] = phone-extension
    public function divPhone(
        string $sLabel,
        array $aFieldNameValues,
        array $aElemAttributes=null,
        ?string $sTooltipMsg='') : string
    {
        if (count($aFieldNameValues) != 3)  // sanity check
        {
            $this->oLog->debug('CHTML: invalid field values array for divPhone() method');
            return '';
        }

        static $iUnique = 0;
        ++$iUnique;
        $aPhoneDescOptions = COptions::phone();
        if (is_null($aElemAttributes)) $aElemAttributes = array();

        $aValues = array();
        $aPostNames = array();          // example: FieldValues[PrimaryPhoneDescription]
        foreach(array_keys($aFieldNameValues) as $index => $sFieldName) // array keys are the field names
        {
            $aPostNames[$index] = $sFieldName;
            $aValues[$index] = $aFieldNameValues[$sFieldName];
        }

        // Add Phone Number placeholder and class for validation
        $aPhoneElemAttributes = array_merge($aElemAttributes ?: array(), array('placeholder' => '555-555-1212'));
        $aPhoneElemAttributes = $this->addClassOption('phone_number', $aPhoneElemAttributes);
        $aPhoneElemAttributes['maxlength'] = 12;

        $this->sLabelAttributes = '';   // elemAttributes() method creates label attributes
        $this->sTooltipAttributes = ''; // elemAttributes() method creates tooltip attributes
        $sIgnore= $this->elementAttributes($aElemAttributes);  // check for required class for <label>, sets variable
        $sTooltip = $this->tooltip($sTooltipMsg);

        $sIDDesc = 'phone_desc_' . $iUnique;
        $sIDPhone = 'phone_no_' . $iUnique;
        $sIDExt = 'phone_ext_' . $iUnique;
        return <<<EOT
            <div class="user_phone">
                <label for="phone_no_{$iUnique}"{$this->sLabelAttributes}>{$sLabel}:</label>
                {$this->select($sIDDesc, $aPostNames[0], $aValues[0], $aPhoneDescOptions, array_merge($aElemAttributes, array('aria-label' => 'Phone Description')))}
                {$this->input('text', $sIDPhone, $aPostNames[1], $aValues[1], $aPhoneElemAttributes)}
                {$this->input('text', $sIDExt, $aPostNames[2], $aValues[2], array('placeholder' => 'Ext.', 'aria-label' => 'Phone extension'))}
                {$sTooltip}
            </div>
        EOT;
    }

    // Position <select> element and Specialty <div> containing checkboxes or experience inputs
    // $aLabels array is comma-separated list of position label and specialty label
    // $aFieldNameValues array (2 elements):
    //  $aFieldNameValues[position-field-name] = position-list-id
    //  $aFieldNameValues[specialty-field-name] = specialty-list-id-1, specialty-list-id-2, ...)
    // A hidden field ensures that specialties field will be cleared if no are items selected
    // Specialty Checkbox Inputs: selections are stored in a DB field as a comma-separated string
    // Specialty Experience Inputs: experience values are stored in a DB field as a json array
    // Javascript shows/hides Specialty options based on Position selection
    public function divSpecialties(
        array $aLabels,                     // [0]=>Position label, [1]=>Specialties label
        array $aFieldNameValues,
        array $aElemAttributes=array(),
        bool $bCheckboxStyle=true,
        string $sToolTip='') : string // checkbox vs text input style
    {
        if (count($aFieldNameValues) != 2)  // sanity check
        {
            $this->oLog->debug('CHTML: invalid field values array for divSpecialties() method');
            return 'Definition Error in divSpecialties';
        }

        // Position and Specialty displayed on site search and personal page
        $sUnique = $bCheckboxStyle ? 'checkbox' : 'input';
        
        // Field Names and Values
        $aKeys = array_keys($aFieldNameValues); // index [0]=position, [1]=specialty]
        $sPositionLabel = $aLabels[0];
        $sPositionFieldName = $aKeys[0];
        $sPositionValue = $aFieldNameValues[$sPositionFieldName];

        $sSpecialtyLabel = $aLabels[1];
        $sSpecialtyFieldName = $aKeys[1];
        $sSpecialtyValue = $aFieldNameValues[$sSpecialtyFieldName];

        // Position <select> options and Specialty options
        $aPositionValues = array('-1' => '--Select Position--');
        $aSpecialtyValues = array();
        $oDBListGeneric = new CDBListGeneric('ListPosition');
        $aRows = $oDBListGeneric->getPositionSpecialties(true);
        foreach($aRows as $aFields)
        {
            $sPositionListID = $aFields['PositionListID'];
            $sSpecialtyListID = $aFields['SpecialtyListID'];
            if (! isset($aPositionValues[$sPositionListID] )) {
                $aPositionValues[$sPositionListID] = $aFields['Position'];
            }
            $aSpecialtyValues[$sPositionListID][$sSpecialtyListID] = intval($sSpecialtyListID) > -1 ?
                $aFields['Specialty'] :'No specialties for this position';
        }

        // Position with specific ID and on-change event to show/hide specialties
        $sPositionClass = 'position_' . $sUnique;       // Position <select> element class
        $sSpecialtyID = 'specialty_' . $sUnique;        // Specialty <select> element ID
        $aPositionAttributes = array_merge(
            $aElemAttributes,
            array('class' => $sPositionClass, 'onchange' => "showSpecialties(this, '{$sSpecialtyID}');"));
        
        // Specialty checkboxes or experience text inputs
        // Used by site search block and personal page: ID's must be unique!
        $aInitCheckboxes = array(); // checkbox specialty IDs for javascript
        if ($bCheckboxStyle)
        {
            // Specialty checkbox inputs: selected specialties are comma-separated list of SpecialtyListIDs
            $aSpecialtyInputs = array();
            $aSelectedSpecialties = is_array($sSpecialtyValue) ? $sSpecialtyValue : explode(',', $sSpecialtyValue);
            foreach($aSpecialtyValues as $iPositionListID => $aSpecialties)
            {
                // <div> containing checkbox and label (javascript shows/hides the <div>)
                foreach($aSpecialties as $iSpecialtyListID => $sSpecialty)
                {
                    if ($iSpecialtyListID > -1)
                    {
                        $bChecked = in_array(strval($iSpecialtyListID), $aSelectedSpecialties);
                        $aInputAttributes = array();
                        if ($bChecked) $aInitCheckboxes[] = $iSpecialtyListID;
                    }
                    else
                    {
                        $bChecked = true;
                        $aInputAttributes = array('disabled' => 'disabled');
                    }
                    $aSpecialtyInputs[] = <<<EOT
                        <div class="specialty position_{$iPositionListID}" style="display:none;">
                            {$this->inputCheckbox($sSpecialty, 'specialty_checkbox', $sSpecialtyFieldName, strval($iSpecialtyListID), $bChecked, $aInputAttributes)}
                        </div>
                    EOT;
                }
            }
            // Specialties scrolling <div> of <div>s checkbox inputs with label
            $sSpecialtiesDiv = $this->divCheckbox($sSpecialtyLabel, $aSpecialtyInputs);
            $sSpecialtiesDiv = str_replace('<div>', '<div id="' . $sSpecialtyID . '" class="scrolling_div">', $sSpecialtiesDiv);
        }
        else
        {
            // Specialty text inputs: selected specialties are json encoded array
            // Indexed of experience values indexed by SpecialtyListID
            $sReset = $this->sPostArrayName;
            $this->sPostArrayName = 'Specialties[' . $sSpecialtyFieldName . ']';    // special case for controller
            $aSpecialtyInputs = array();
            $aSelectedSpecialties = json_decode($sSpecialtyValue, true);
            foreach($aSpecialtyValues as $iPositionListID => $aSpecialties)
            {
                // <div> containing text input and speciality (javascript shows/hides the <div>)
                $aInputAttributes = array(
                    'class' => 'specialty_experience', 'min' => '0', 
                    'oninput' => "this.value = this.value < 0 ? 0 : this.value;",       // prevent negative number
                    'onblur' => "this.value = this.value == '' ? 0 : this.value;");     // prevent blank
                foreach($aSpecialties as $iSpecialtyListID => $sSpecialty)
                {
                    $sValue = isset($aSelectedSpecialties[$iSpecialtyListID]) ? $aSelectedSpecialties[$iSpecialtyListID] : 0;
                    $aInputAttr = ($iSpecialtyListID > -1) ? $aInputAttributes : array_merge($aInputAttributes, array('disabled' => 'disabled'));
                    $aSpecialtyInputs[] = <<<EOT
                        <div class="specialty position_{$iPositionListID}" style="display:none;">
                            {$this->input('number', 'specialty_experience', strval($iSpecialtyListID), strval($sValue), $aInputAttr)}
                            {$sSpecialty} 
                        </div>
                    EOT;
                }
            }
            // Specialties scrolling <div> of <div>s containing text inputs and prompt
            $sSpecialtyDiv = implode('', $aSpecialtyInputs);
            $sSpecialtiesDiv = <<<EOT
                <div>
                    <label>{$sSpecialtyLabel}:</label>
                    <div id="{$sSpecialtyID}" class="scrolling_div" style="top:-24px;">
                        {$sSpecialtyDiv}
                    </div>
                </div>
            EOT;
            $this->sPostArrayName = $sReset;
        }

        // Position <div>, Specialty <div> and initialize the Specialties selections
        $this->bAddUniqueID = false;    // unique ids already created for javascript
        $this->addTooltip($sPositionFieldName, $sToolTip);
        $sInitCheckboxes = implode(',', $aInitCheckboxes);   // comma-separated specialty ids for javascript
        $sHTML = <<<EOT
            {$this->divSelect($sPositionLabel, $sPositionFieldName, $sPositionValue, $aPositionValues, $aPositionAttributes)}
            {$sSpecialtiesDiv}
            <script>
                window.addEventListener('load', initSpecialties('{$sPositionClass}', '{$sSpecialtyID}', '{$sInitCheckboxes}'));
            </script>
        EOT;
        $this->bAddUniqueID = false;
        return $sHTML;
    }

    // Submit verify Date and current User with "Verify" button
    // Clear the fields if the "Verify" button is not submitted (i.e. on "save")
    // Fields are posted with a "Verify" post name for controller (i.e. Verify[field-name])
    public function divVerify(
        array $aFieldNameValues,
        array $aElemAttributes,
        string $sAjaxButton,
        string $sUserPermission) : string
    {
        // Sanity Check: expect Verified Date, Verified By User Account ID, Document IDs and Verified User Name fields
        // May include multiple file IDs
        if (count($aFieldNameValues) < 4)
        {
            $this->oLog->debug('Invalid number of parameters for verify element: expected at least 4, received ' . count($aFieldNameValues));
            return '';
        }

        $aFieldNames = array_keys($aFieldNameValues);
        $aFieldValues = array_values($aFieldNameValues);

        $indexVerifyDate = 0;                           // first field
        $indexUserAccountID = 1;                        // second field
        $indexDocumentFileID1 = 2;                      // third field up to last field (1 or more documents)
        $indexUserName = count($aFieldNameValues) - 1;  // last field

        // View document buttons
        $sViewButtons = '';
        for($index = $indexDocumentFileID1; $index < $indexUserName; ++$index)
        {
            // Link to display the image in a new window
            $i = 0;
            if (isset($aFieldValues[$index]) && ($aFieldValues[$index] != '-1'))
            {
                $sDisplayFileURL = CFileManager::getDisplayURL(CValidate::validID($aFieldValues[$index]));
                $sOnClick = CHTMLForm::onClickHandlerNewWindow($sDisplayFileURL);
                $sViewButtons .= '<button type="button" style="margin-right: 10px;" onclick="' . $sOnClick . '">Document ' . ++$i . '</button>';
            }
        }
        $sViewDocuments = $sViewButtons != '' ? '<label>View Documents:</label>' . $sViewButtons : '';

        // Verify button or verification information
        $sReset = $this->sPostArrayName;        // special post array for controller
        $this->sPostArrayName = 'Verify';
        $aElemAttributes['readonly'] = 'readonly';
        $aElemAttributes['disabled'] = 'disabled';
        $sHTML = '';
        if ((strlen($aFieldValues[$indexVerifyDate]) == 0) || ($aFieldValues[$indexVerifyDate] == '0000-00-00 00:00:00'))
        {
            // Not verified: submit current date and current User Account ID with Ajax button
            // Create buttons to view each document in a new browser window
            $sInstructions = CUserCurrent::isUserMSP() ?
                '<p><b>INSTRUCTIONS:</b> Save this information. After entering all items, upload the verification documents.  This item can then be verified.</p>' :
                '<p>This document has not been verified.</p>';
            $sHTML = <<<EOT
                <h3>Verify Information</h3>
                {$sInstructions}
                {$sViewDocuments}
            EOT;
            // MSP user or Davin Subcontractor User with permission
            if (($sUserPermission != CPermissions::$sPermissionNo) &&
                (CUserCurrent::isUserMSP() || CUserCurrent::isUserDavinSubcontractor()))
            {
                $sHTML .= <<<EOT
                    <div>
                        {$sAjaxButton}
                        {$this->divInput('date', 'Verification Date', 'VerificationMsg', date('Y-m-d'), $aElemAttributes)}
                        {$this->hidden($aFieldNames[$indexVerifyDate], date('Y-m-d H:i:s'))}
                        {$this->hidden($aFieldNames[$indexUserAccountID], strval(CUserCurrent::userAccountID()))}
                    </div>
                    <div style="clear:both;"></div>
                EOT;
            }
        }
        else
        {
            // Verified: post the blank fields on "save" to clear the verified information (no further action required by the controller)
            $sVerifiedMsg = date('m/d/Y h:m A',strtotime($aFieldValues[$indexVerifyDate])) . (strlen($aFieldValues[$indexUserName]) > 0 ? ' By ' . $aFieldValues[$indexUserName] : '');
            $this->addTooltip($aFieldNames[$indexVerifyDate],"Verification is performed by the system administrator.");
            $sHTML = <<<EOT
                <h3>Verify Information</h3>
                {$sViewDocuments}
                {$this->divInputText('Verified', 'VerifiedMsg', $sVerifiedMsg, $aElemAttributes)}
                {$this->hidden($aFieldNames[$indexVerifyDate], 'NULL')}
                {$this->hidden($aFieldNames[$indexUserAccountID], '')}
            EOT;
        }
        $this->sPostArrayName = $sReset;
        return $sHTML;
    }

    // Submit obsolete value, date and current User with "Obsolete" button
    // Fields are posted with a "Obsolete" post name for controller (i.e. Obsolete[field-name])
    // Obsolete records are only visible to MSP users
    public function divObsolete(
        array $aLabels,
        array $aFieldNameValues,
        array $aElemAttributes,
        string $sAjaxButton,
        string $sUserPermission) : string
        {
            // Sanity Check: expect IsObsolete, Verified Date, Verified By User Account ID and Obsolete User Name fields
            if (count($aFieldNameValues) != 4)
            {
                $this->oLog->debug('Invalid number of parameters for obsolete element: expected 3, received ' . count($aFieldNameValues));
                return '';
            }

            $aFieldNames = array_keys($aFieldNameValues);
            $aFieldValues = array_values($aFieldNameValues);

            // Is Obsolete radio buttons with hidden date and user account ID
            $sHTML = '';
            $sReset = $this->sPostArrayName;        // special post array for controller
            $this->sPostArrayName = 'Obsolete';
            $aElemAttributes['readonly'] = 'readonly';
            $aElemAttributes['disabled'] = 'disabled';
            $this->addTooltip('ObsoleteMsg', 'Obsolete records contain information that is no longer valid or necessary.');
            if ($aFieldValues[0] != 'Y')
            {
                // Not obsolete: submit current date and current User Account ID with Ajax button
                // Controller will check for the ajax button
                $sButton = '';
                if (CUserCurrent::isUserMSP() && ($sUserPermission != CPermissions::$sPermissionNo))
                {
                    $sButton = <<<EOT
                        {$sAjaxButton}
                        {$this->hidden($aFieldNames[0], 'Y')}
                        {$this->hidden($aFieldNames[1], date('Y-m-d'))}
                        {$this->hidden($aFieldNames[2], strval(CUserCurrent::userAccountID()))}
                    EOT;
                    $sHTML = <<<EOT
                        <div>
                            <p>Information is not obsolete.</p>
                            {$sButton}
                        </div>
                        <div style="clear:both;"></div>
                    EOT;
                }
            }
            else
            {
                // Is obsolete: submit current date and current User Account ID with Ajax button
                // Controller will check for the ajax button
                $sButton = '';
                if (CUserCurrent::isUserMSP() && ($sUserPermission != CPermissions::$sPermissionNo))
                {
                    $sAjaxButton = str_replace('Obsolete</button>', 'Restore</button>', $sAjaxButton);   // change button text
                    $sButton = <<<EOT
                        {$sAjaxButton}
                        {$this->hidden($aFieldNames[0], 'N')}
                        {$this->hidden($aFieldNames[1], date('Y-m-d'))}
                        {$this->hidden($aFieldNames[2], strval(CUserCurrent::userAccountID()))}
                    EOT;
                }
                $sObsoleteMsg = (strlen($aFieldValues[1]) > 0) && (strlen($aFieldValues[3]) > 0) ?
                    ': ' . date('m/d/Y h:m A',strtotime($aFieldValues[1])) . (strlen($aFieldValues[3]) > 0 ? ' By ' . $aFieldValues[3] . '.' : '') : '';
                $sHTML = <<<EOT
                    <div style="width:600px;">
                        Information is obsolete {$sObsoleteMsg}
                        {$sButton}
                    </div>
                    <div style="clear:both;"></div>
                EOT;
            }
            $this->sPostArrayName = $sReset;
            return $sHTML;
    }

    // Check if the caregiver is blocked 
    // MSP user only, at any active facility by any subcontractor
    public function caregiverIsBlocked(int $iCaregiverUserAccountID) : string
    {
        $sDivIsBlocked = '';
        if (CUserCurrent::isUserMSP() || CUserCurrent::isUserDavinSubcontractor())
        {
            // Potentially no caregiver user account ID passed in if no cleared facility records
            if ($iCaregiverUserAccountID == -1) $iCaregiverUserAccountID = CPageCurrent::caregiverID();

            $oDBCaregiverFacility = new CDBCaregiverFacility();
            $aRows = $oDBCaregiverFacility->isBlocked($iCaregiverUserAccountID);
            if (count($aRows) > 0)
            {
                $sDivIsBlocked .= '<div><h3>Caregiver is blocked at:</h3><ul style="margin: 0 0 20px 20px;">';
                foreach($aRows as $aFields)
                {
                    $sDivIsBlocked .= "<li>{$aFields['FacilityName']} by {$aFields['SubcontractorName']}</li>";
                }
                $sDivIsBlocked .= '</ul></div>';
            }
        }
        return $sDivIsBlocked;
    }

    // ----------------------------------------------------
    // Ajax Button to submit the form
    // ----------------------------------------------------

    // Navigation <div> with button to submit the form via ajax javascript method
    //  $sButtonText: button display text
    //  $sFormID: element ID of form to be validated and submitted
    //  $sElemAttributes: button formatting attributes
    //  $sContainerID: container element to be replaced by ajax submit ('PARENT' indicates form's parent element)
    // Include traditional submit button if constant('DAVIN_AJAX') is true
    public function navAjaxButton(string $sButtonText, string $sFormID, array $aElemAttributes=null, string $sContainerID = 'PARENT') : string
    {
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sDebugAjax = constant('DAVIN_AJAX') ?
            '<button type="submit" name="PostButton" value="AjaxSave" ' . $sAttributes . '>Submit ' . $sButtonText . '</button>' : '';

        return <<<EOT
            <div class="navigation">
                <button type="button" name="AjaxSave" value="AjaxSave" onclick="formSubmit(this, '{$sFormID}', '{$sContainerID}');" {$sAttributes}>{$sButtonText}</button>
                {$sDebugAjax}
            </div>
        EOT;
    }

    // Ajax form submit button
    public function ajaxButton(string $sButtonName, string $sButtonText, string $sFormID, array $aElemAttributes=null, string $sContainerID = 'PARENT') : string
    {
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sDebugAjax = constant('DAVIN_AJAX') ?
            '<button type="submit" name="PostButton" value="' . $sButtonName . '" ' . $sAttributes . '>Submit ' . $sButtonText . '</button>' : '';

        return <<<EOT
            <button type="button" name="{$sButtonName}" value="{$sButtonName}" onclick="formSubmit(this, '{$sFormID}', '{$sContainerID}');" {$sAttributes}>{$sButtonText}</button>
            {$sDebugAjax}
        EOT;
    }

    // Save New Record: submit <form> button destination URL to save a new record from a non-popup form page
    public function formSubmitNewSelf(string $sButtonName, string $sButtonText, array $aElemAttributes=null) : string
    {
        $sAttributes = $this->elementAttributes($aElemAttributes);
        return 
            '<button type="submit" name="PostButton" value="' . $sButtonName . '" ' . $sAttributes . '>' . $sButtonText . '</button>';
    }

    // Navigation <div> with button to submit to the URL provided in the onClick handler
    // Ajax form submit with confirm button: javascript checks for changed data and prompts for confirm ("OK" continues without saving changes)
    public function ajaxFormPage(string $sButtonName, string $sButtonText, string $sOnClickHandler) : string
    {
        static $sAria = 'aria-expanded="false" aria-controls="generic_popup_form"';
        static $iButtonID = 1;
        $sButtonIdentifier = str_replace("/","",str_replace("\"","",str_replace(" ","",$sButtonText)));
        $iButtonID++;
        return <<<EOT
            <button id="AjaxButton{$sButtonIdentifier}{$sButtonName}{$iButtonID}" type="button" name="{$sButtonName}" value="{$sButtonName}" {$sAria} onclick="{$sOnClickHandler}">{$sButtonText}</button>
        EOT;
    }

    // Navigation <div> with button to submit to the URL provided in the onClick handler
    // Ajax form submit with confirm button: javascript checks for changed data and prompts for confirm ("OK" continues without saving changes)
    public function ajaxFormPageDBField(string $sButtonName, string $sButtonText, string $sOnClickHandler, string $sTitle = '') : string
    {
        static $sAria = 'aria-expanded="false" aria-controls="generic_popup_form"';
        static $iButtonID = 1;
        $iButtonID++;
        $sButtonIdentifier = str_replace("/","",str_replace("\"","",str_replace(" ","",$sButtonText)));
        return <<<EOT
            <button id="AjaxButton{$sButtonIdentifier}{$sButtonName}{$iButtonID}" class="subtleLink" type="button" title="{$sTitle}" name="{$sButtonName}" value="{$sButtonName}" {$sAria} onclick="{$sOnClickHandler}">{$sButtonText}</button>
        EOT;
    }

    // Save New Record: submit <form> button to a different destination URL to save a new record from a popup form
    public function formSubmitNewDestination(string $sButtonText, string $sFormID, string $sTargetURL, array $aElemAttributes=null) : string
    {
        $sAttributes = $this->elementAttributes($aElemAttributes);
        return<<<EOT
            <button type="button" name="AjaxSave" value="AjaxSave" onclick="formSubmitNew(this, '{$sFormID}', '{$sTargetURL}');" {$sAttributes}>{$sButtonText}</button>
        EOT;
    }

    // Delete Record: submit <form> button to different destination URL to delete a new record from a popup form
    public function formSubmitDelete(string $sButtonText, string $sFormID, string $sTargetURL, array $aElemAttributes=null) : string
    {
        $sAttributes = $this->elementAttributes($aElemAttributes);
        return<<<EOT
            <button type="button" name="AjaxDelete" value="AjaxDelete" onclick="formSubmitDelete(this, '{$sFormID}', '{$sTargetURL}');" {$sAttributes}>{$sButtonText}</button>
        EOT;
    }

    // Close Modal <div>: button to close the modal <div>, prompt if data changed
    public function formClose(string $sButtonText='Close', array $aElemAttributes=null) : string
    {
        $sAttributes = $this->elementAttributes($aElemAttributes);
        return<<<EOT
            <button type="button" class="close" name="Close" value="Close" onclick="formClose();" {$sAttributes}>{$sButtonText}</button>
        EOT;
    }

    // ----------------------------------------------------
    // Display Information
    // ----------------------------------------------------

    public static function controllerMsgs(string $sControllerSuccess=null, string $sControllerError=null) : string
    {
        return
            (((! is_null($sControllerSuccess)) && (strlen($sControllerSuccess) > 0)) ? '<p class="success">' . $sControllerSuccess . '</p>' : '') .
            (((! is_null($sControllerError)) && (strlen($sControllerError) > 0)) ? '<p class="error">' . $sControllerError . '</p>' : '');
    }

    // Record created-by and updated-by information <div> (MSP users only)
    public static function recordInfo(string $sCreatedBy, string $sCreatedAt, string $sUpdatedBy, string $sUpdatedAt) : string
    {
        $oValidate = new CValidate();

        if ((! CUserCurrent::isUserMSP()) || ($sCreatedBy == '')) {
            $sUpdated = $sUpdatedBy ? "Updated by {$sUpdatedBy} on {$oValidate->toDisplayDateTime($sUpdatedAt)}" : '';
             return <<<EOT
                <div class="record_info">
                    {$sUpdated}
                </div>
                <div style="clear:both;"></div>
            EOT;
        }

        $sCreated = "Created by {$sCreatedBy} on {$oValidate->toDisplayDateTime($sCreatedAt)}";
        $sUpdated = $sUpdatedBy ? "<br />Updated by {$sUpdatedBy} on {$oValidate->toDisplayDateTime($sUpdatedAt)}" : '';
        return <<<EOT
            <div class="record_info">
                {$sCreated}
                {$sUpdated}
            </div>
            <div style="clear:both;"></div>
        EOT;
    }

    // Activated-by/Deactivated-by information <div>
    public function activatedInfo(string $sActivatedBy, string $sActivatedAt, string $sDeactivatedBy, string $sDeactivatedAt, string $sMethod) : string
    {
        $sDeactivated = '';
        $oValidate = new CValidate();
        $sActivated = $sActivatedBy ? "Activated by {$sActivatedBy} on {$oValidate->toDisplayDateTime($sActivatedAt)} </br>" : '';
        // Check if deactivated in V1 or V2
        $sDeactivatedVersion = 'V2';
        if (strlen($sDeactivatedAt) > 0 && strtotime($sDeactivatedAt) < strtotime('2024-04-25 00:00:01')) {
            $sDeactivatedVersion = 'V1';
        }
        if(!$sDeactivatedBy && $sDeactivatedVersion == 'V1') {
            // If not deactived in V2, put V1 C5 $sDeactivatedByC5
            $sDeactivated = "Deactivated {$sDeactivatedVersion}:{$sMethod} on {$oValidate->toDisplayDateTime($sDeactivatedAt)}";
        } elseif($sDeactivatedBy) {
            // Deactivated in V2
            $sDeactivated = "Deactivated {$sDeactivatedVersion}:{$sMethod} by {$sDeactivatedBy} on {$oValidate->toDisplayDateTime($sDeactivatedAt)}";
        } else {
            $sDeactivated = '';
        }
        return <<<EOT
            <div class="activated_info">
                {$sActivated}
                {$sDeactivated}
            </div>
        EOT;
    }

    // Create the <div> with a link to the page
    public function divLink(string $sLabel, string $sTitle, string $sLinkedDirectory, array $aElemAttributes=null) : string
    {
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sLabelDiv = $sLabel == '' ? "":"
            <label style=\"padding-top: 0px;\">
                {$sLabel}:
            </label>";
        return <<<EOT
            <div {$sAttributes}>
                {$sLabelDiv}
                {$sPrefix}
                <a href="{$sLinkedDirectory}">
                    {$sTitle}
                </a>
                {$sSuffix}
            </div>
        EOT;
    }

    // Create the <div> with a link to the page
    public function divOnClick(string $sLabel, string $sTitle, string $sLinkedDirectory, array $aElemAttributes=null) : string
    {
        $sPrefix = isset($aElemAttributes['prefix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['prefix']."</span>":'';
        $sSuffix = isset($aElemAttributes['suffix']) ? "<span class=\"{$aElemAttributes['class']}\" style=\"\">".$aElemAttributes['suffix']."</span>":'';
        unset($aElemAttributes['prefix']);
        unset($aElemAttributes['suffix']);
        $sAttributes = $this->elementAttributes($aElemAttributes);
        $sLabelDiv = $sLabel == '' ? "":"
            <label style=\"padding-top: 0px;\">
                {$sLabel}:
            </label>";
        return <<<EOT
            <div {$sAttributes}>
                {$sLabelDiv}
                {$sPrefix}
                <a onclick="{$sLinkedDirectory}">
                    {$sTitle}
                </a>
                {$sSuffix}
            </div>
        EOT;
    }

    public function spanMore(string $sContent, int $iLength) : string
    {
        if (strlen($sContent) < $iLength) return $sContent;

        static $iUnique = 0;
        ++$iUnique;

        $sShow = substr($sContent, 0, $iLength);                        // trim content to length
        if (($iPos = strpos($sShow, ' ', -15)) !== false)
        {
            $sShowContent = substr($sShow, 0, $iPos);    // find last space character
            $sHideContent = substr($sContent, strlen($sShowContent));       // hide remainder of content
        }

        $sMoreID = 'more_' . $iUnique;
        $sLessID = 'less_' . $iUnique;
        return <<<EOT
            {$sShowContent}
            <span id="{$sLessID}" style="display:none;">
                {$sHideContent}
                <a onclick=
                    "document.getElementById('{$sLessID}').style.display='none';
                    document.getElementById('{$sMoreID}').style.display='initial';">(Less)</a>
            </span>
            <a id="{$sMoreID}" style="display:inline;" 
                onclick=
                    "document.getElementById('{$sLessID}').style.display='initial';
                    document.getElementById('{$sMoreID}').style.display='none';">(More...)</a>
            EOT;
    }

    //--------------------------------------------------------------
    // Tooltip Options - Still needs to be refined more, will do-rico
    //-------------------------------------------------------------

    // Add a tooltip to the array indexed by element name
    public function addTooltip(string $sElemName, string $sTooltipHTML) : void
    {
        $this->aTooltips[$sElemName] = $sTooltipHTML;
    }

    // Return a tooltip for this element name
    private function getTooltip(string $sElemName) : string
    {
        return isset($this->aTooltips[$sElemName]) ? $this->Tooltip($this->aTooltips[$sElemName]) : '';
    }

    public function TooltipWarning($sMsg, $sChar = '!', $bValidChar=false)
    {
        return $this->Tooltip($sMsg, $sChar, $bValidChar, true);
    }

    public function Tooltip($sMsg, $sChar = '?', $bValidChar=false, $bWarning=false,$sAddClass = '') : string
    {
        if (strlen($sMsg) == 0) return '';
        $sID = 'tip_' . strval(++$this->iTooltipID) . '_' . strval(random_int(1000, 9999));

        $sJSHandler = '';
        if ($this->iTooltipID == 1)
        {
            $sJSHandler = <<<EOT
                <script type="text/javascript">
                var prevTip= null;
                function TooltipDetails(sID,bOpen)
                {
                    var currentObj = document.getElementById(sID);
                    var closeObj = document.getElementById('close_' + sID);
                    currentObj.style.display = 'block';
                    var containerElRect = currentObj.getBoundingClientRect();
                    currentObj.style.display = 'none';
                    var offsetLeft = containerElRect.left;
                    var offsetRight = containerElRect.right;
                    var boxWidth = containerElRect.width;
                    if (prevTip != null && !bOpen)
                    {
                        prevTip.style.display = 'none';
                        if (prevTip.id != currentObj.id)
                        {
                            currentObj.style.display = 'block';
                            prevTip = currentObj;
                        }
                        else
                        {
                            prevTip = null;
                        }
                    }
                    else
                    {
                        if(offsetLeft < offsetRight){
                            if(offsetLeft < 200){
                                currentObj.style.left = '-100px';
                            }
                        }else if(offsetRight < 200){
                            currentObj.style.left = '-'+ (boxWidth - 100) +'px';
                        }
                        currentObj.style.display = 'block';
                        if(!bOpen)closeObj.style.display = 'block';
                        prevTip = currentObj;
                    }
                }
                </script>
            EOT;
        }

        if(!$bValidChar) $sChar = CValidate::validString($sChar);
        if(!$bValidChar) $sMsg  = CValidate::validString($sMsg);
        $iLimit = 400;
        if(strpos($sMsg, ') AND (')){
            $sMsg = str_replace(') AND (', ') AND</br> (', $sMsg);
            $iLimit = 600;
        }
        $sWarningClass = $bWarning ? ' Warning' : '';

        $iPxPerChar = 16;
        $iMsgLen = strlen($sMsg);
        $iWidth = min($iMsgLen * $iPxPerChar, $iLimit);
        $iLeft = -(floor($iWidth / 2));     // center the <p> over the <div>
        if($iMsgLen<80){
            $iHeight = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 17);
        }elseif($iMsgLen<300){
            $iHeight = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 14);
        }elseif($iMsgLen<500){
            $iHeight = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 7);
        }else{
            $iHeight = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 5);
        }
        $iHeight += 65; // button height

        // Tooltip class and style attributes
        $sTooltipClass = "tooltip-wrap{$sWarningClass}";
        $sTooltipStyle = '';
        if ($this->sTooltipAttributes == '')    // set by elemAttributes() method
        {
            $sAttributes = "class=\"{$sTooltipClass} {$sAddClass}\"";
        }
        else
        {
            // Add the tooltip class and tooltip style to existing class and style attributes
            // if tooltip attributes have already been set by the element information
            $sAttributes = 
                str_replace('class="', 'class="' . $sTooltipClass . ' ', 
                str_replace('style="', 'style="' . $sTooltipStyle, $this->sTooltipAttributes)) ;
            if (! str_contains($sAttributes, 'class="')) $sAttributes .= ' class="' . $sTooltipClass . '"';
            if (! str_contains($sAttributes, 'style="')) $sAttributes .= ' style="' . $sTooltipStyle . '"';
        }

        return <<<EOT
            {$sJSHandler}
            <span {$sAttributes} data-attribute="{$sChar}" onclick="TooltipDetails('{$sID}',false);" onmouseenter="TooltipDetails('{$sID}',true);" onmouseleave="TooltipDetails('{$sID}',false);">{$sChar}
                <p id="{$sID}" style="width:{$iWidth}">
                    {$sMsg}
                    <button type="button" id="close_{$sID}" name="close" style="display:none;" aria-label="Close Tooltip">Close</button>
                </p>
            </span>
        EOT;
    }

    static public function BubbleTooltip($sMsg, $sChar = '?',$bValidChar=false)
    {
        if (strlen($sMsg) == 0) return '';
        static $iID = 0;

        $sJSHandler = '';
        if ($iID == 0)
        {
            $sJSHandler = <<<EOT
                <script type="text/javascript">
                var prevTip= null;
                function TooltipDetails(sID)
                {
                    currentObj = document.getElementById(sID);
                    if (prevTip != null)
                    {
                        prevTip.style.display = 'none';
                        if (prevTip.id != currentObj.id)
                        {
                            currentObj.style.display = 'block';
                            prevTip = currentObj;
                        }
                        else
                        {
                            prevTip = null;
                        }
                    }
                    else
                    {
                        currentObj.style.display = 'block';
                        prevTip = currentObj;
                    }
                }
                </script>
EOT;
        }

        if(!$bValidChar) $sChar = CValidate::OutputString($sChar);
        if(!$bValidChar) $sMsg  = CValidate::OutputString($sMsg);
        $iLimit = 400;
        if(strpos($sMsg, ') AND (')){
            $sMsg = str_replace(') AND (', ') AND</br> (', $sMsg);
            $iLimit = 600;
        }

        $sID        = 'Tip-' . ++$iID;
        $iPxPerChar = 16;
        $iMsgLen    = strlen($sMsg);
        $iWidth     = min($iMsgLen * $iPxPerChar, $iLimit);

        if($iMsgLen<30){
            $iHeight    = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 10) + 5;
        }elseif($iMsgLen<80){
            $iHeight    = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 17) + 5;
        }elseif($iMsgLen<300){
            $iHeight    = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 14) + 5;
        }elseif($iMsgLen<500){
            $iHeight    = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 7) + 5;
        }else{
            $iHeight    = (floor(($iMsgLen * $iPxPerChar) / $iWidth) * 5) + 5;
        }
        $iLeft      = -(floor($iWidth / 2));                               // center the <p> over the <div>

        $sMsg = self::AddLink($sMsg);
        return <<<EOT
            {$sJSHandler}
            <span class="tooltip-wrap" onclick="TooltipDetails('{$sID}');" style="border-radius:5px;display:inline-block">{$sChar}
                <p id="{$sID}" style="min-width:{$iWidth}px;min-height:{$iHeight}px;max-height:{$iHeight}px;left:{$iLeft}px;border-radius:2px">{$sMsg}
                <br /><button aria-label="click to close the tooltip" style="margin-top:5px;">Close</button></p></span>
EOT;
    }

    // Create an HRef link from the http:// string
    static public function AddLink($sMsg, $bBlankTarget = false)
    {
        if (($iStart = strpos($sMsg, 'http://')) === false) return $sMsg;

        $sTarget = $bBlankTarget ? ' target="_BLANK"' : '';

        $iEnd  = strpos($sMsg, ' ', $iStart);
        $sLink = substr($sMsg, $iStart, $iEnd - $iStart);
        return str_replace($sLink, "<a href=\"{$sLink}\"{$sTarget}>{$sLink}</a>", $sMsg);
    }

    // ----------------------------------------------------
    // Private helper functions
    // ----------------------------------------------------

    // Create the element ID from the element name (convert camel case to lowercase w/ underscore separator)
    // Include the ['id'] element in the Element Attributes to specify a specific ID for the element
    protected function createElementID(string $sElemName, array &$aElemAttributes=null)
    {
        // Element ID is specified in the Elements Attributes array: use it
        if ((! is_null($aElemAttributes)) && (isset($aElemAttributes['id'])))
        {
            $sReturnID = $aElemAttributes['id'];
            unset($aElemAttributes['id']);
        }
        else    // convert from camel case to lowercase with underscores
        {
            $sReturnID = strtolower(
                preg_replace('/(?<!^)[A-Z]+|(?<!^|\d)[\d]+/', '_$0',
                    str_replace('[', '_',
                        str_replace(']', '',
                            str_replace('.', '', 
                                str_replace("'", '', $sElemName))))));
        }
        return $sReturnID;
    }

    // Create the element name by surrounding the element name value with the $sPostArrayName
    private function createPostName(string $sElemName) : string
    {
        if ($this->sPostArrayName != '')
        {
            if (strpos($sElemName, '[]') !== false) // special case: element name is an array
            {
                // Post Array Name: default to "Multiple" unless specific Post Array Name has been set
                $sPostName = $this->sPostArrayName == self::DEFAULT_POST_ARRAY ? 'Multiple' : $this->sPostArrayName;
                $sElemName = "{$sPostName}[" . str_replace('[]', '][]', $sElemName);
            }
            else if (strpos($sElemName, '[') !== false) // special case: element name is an array
            {
                $sPostName = $this->sPostArrayName == self::DEFAULT_POST_ARRAY ? 'Multiple' : $this->sPostArrayName;
                $sElemName = "{$sPostName}[" . str_replace('[', '][', $sElemName);
            }
            else
            {
                $sElemName = "{$this->sPostArrayName}[{$sElemName}]";
            }
        }
        return $sElemName;
    }

    // Remove element attributes that should not be assigned to the <div>
    protected function cleanDivAttributes(array $aAttributes=null) : array
    {
        if (is_null($aAttributes)) return array();
        if (isset($aAttributes['onchange'])) unset($aAttributes['onchange']);
        if (isset($aAttributes['onkeyup'])) unset($aAttributes['onkeyup']);
        if (isset($aAttributes['onkeydown'])) unset($aAttributes['onkeydown']);
        if (isset($aAttributes['onpaste'])) unset($aAttributes['onpaste']);
        return $aAttributes;
    }

    // Create the string of options from the element options array
    // NOTE: null default assignment allows a null value to be passed
    protected function elementAttributes(array $aElemAttributes=null) : string
    {
        // No options: return
        $aElemAttributes = is_null($aElemAttributes) ? array() : $aElemAttributes;
        $this->aIncludeAttributes = is_null($this->aIncludeAttributes) ? array() : $this->aIncludeAttributes;
        if ((count($aElemAttributes) == 0) && (count($this->aIncludeAttributes) == 0)) return '';

        // Combine the input element attributes array with the class attributes array for all elements
        foreach($this->aIncludeAttributes as $sKey => $sValue)
        {
            switch(strtolower($sKey))
            {
                case 'class':       // add css class to the 'class' attribute (may be multiple classes)
                    $aElemAttributes = $this->addClassOption($sValue, $aElemAttributes);
                    break;
                case 'style':       // add css style to the 'style' attribute (may be multiple styles)
                    $aElemAttributes = $this->addStyleOption($sValue, $aElemAttributes);
                    break;
                case 'onchange':    // add onChange() to the 'onchange' attribute (may be multiple styles)
                    $aElemAttributes = $this->addOnChangeOption($sValue, $aElemAttributes);
                    break;
                default:
                    if (strlen($sValue) > 0) $aElemAttributes[$sKey] = $sValue;
            }
        }
        ksort($aElemAttributes);

        $sAttributes = '';
        foreach ($aElemAttributes as $sOption => $sValue)
        {
            if (strlen((string)$sValue) > 0)
            {
                $sAttributes .= " {$sOption}=\"{$sValue}\"";
                if (($sOption == 'class') && str_contains($sValue, 'required'))
                {
                    // Special case: Required class for this element
                    // Add class='label_required' css class to the <label> element (red * from css)
                    $this->sLabelAttributes .= ' class="label_required"';
                }
                if (($sOption == 'class') && str_contains($sValue, 'MatchCondition'))
                {
                    // Special case: show/hide tooltip based on element's conditional (add condition class)
                    $this->sTooltipAttributes .= ' class="' . $sValue . '"';
                }
                if (($sOption == 'style') && str_contains($sValue,"display:none;"))
                {
                    // Special case: hide label and tooltip if element is hidden
                    $this->sLabelAttributes .= ' style="display:none;"';
                    $this->sTooltipAttributes .= ' style="display:none;"';
                }
            }
        }
        return $sAttributes;
    }

    // Add a css class to the 'class' attribute of the element attributes array (may be multiple classes)
    protected function addClassOption(string $sClassName, array $aElemAttributes=null) : array
    {
        if (is_null($aElemAttributes))             // add array element
        {
            $aElemAttributes = array('class' => $sClassName);
        }
        elseif (isset($aElemAttributes['class']))  // append to existing class element
        {
            $aElemAttributes['class'] .= ' ' . $sClassName;
        }
        else                                    // add array element
        {
            $aElemAttributes['class'] = $sClassName;
        }
        return $aElemAttributes;
    }

    protected function removeClassOption(string $sOption, array $aElemAttributes) : array
    {
        foreach($aElemAttributes as $sKey => $sValue)
        {
            if ($sKey == 'class')
            {
                if ($sValue == $sOption)
                {
                    unset($aElemAttributes['class']);
                }
                elseif (strpos($sValue, $sOption) !== false)
                {
                    $aElemAttributes['class'] = trim(str_replace($sOption, '', $aElemAttributes['class']));
                }
            }
        }
        return $aElemAttributes;
    }

    protected function addStyleOption(string $sStyles, array $aElemAttributes=null)
    {
        if (is_null($aElemAttributes))             // add array element
        {
            $aElemAttributes = array('style' => $sStyles);
        }
        elseif (isset($aElemAttributes['style']))  // append to existing style element
        {
            $aElemAttributes['style'] .= ';' . $sStyles;
        }
        else                                    // add array element
        {
            $aElemAttributes['style'] = $sStyles;
        }
        return $aElemAttributes;
    }

    protected function addMinWidthStyleOption(string $sElemValue, array $aElemAttributes=null) : array
    {
        if (is_null($aElemAttributes)) $aElemAttributes = array();
        if (strlen($sElemValue) > 0)
        {
            $aElemAttributes = $this->addStyleOption('min-width:' . strlen($sElemValue) . 'ch', $aElemAttributes);
        }
        return $aElemAttributes;
    }


    protected function addOnChangeOption(string $sOnChange, array $aElemAttributes=null)
    {
        if (is_null($aElemAttributes))             // add array element
        {
            $aElemAttributes = array('onchange' => $sOnChange);
        }
        elseif (isset($aElemAttributes['onchange']))  // append to existing style element
        {
            $aElemAttributes['onchange'] .= ';' . $sOnChange;
        }
        else                                    // add array element
        {            
            $aElemAttributes['onchange'] = $sOnChange;
        }
        return $aElemAttributes;
    }

    protected function downloadFileLink(string $sElemValue) : string
    {
        $iFileID = CValidate::validID($sElemValue);
        return ($iFileID > -1) ? CFileManager::getFileLink($iFileID, 'View File') : '';
    }

    // Return a hidden <div> to be toggle by the <a> tag suffix
    private function getToggleDiv(string $sContents, string &$sBtn) : string
    {
        static $iUniqueID = 0;

        $sDivID = 'generic_toggle_div_' . ++$iUniqueID;
        $sBtn .= "<button type=\"button\" name=\"toggle_div\" class=\"toggle_div\" onclick=\"toggleElement('{$sDivID}');\">View Account Details</button>";
        return <<<EOT
            <div id="{$sDivID}" class="toggle_div" style="display:none;margin-bottom:6px;">
                {$sContents}
            </div>
        EOT;
    }

}   // end class
