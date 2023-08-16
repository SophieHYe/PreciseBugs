<?php
	/*
	 *	Copyright 2007 John Oren
	 *
	 *	Licensed under the Apache License, Version 2.0 (the "License");
	 *	you may not use this file except in compliance with the License.
	 *	You may obtain a copy of the License at
	 *		http://www.apache.org/licenses/LICENSE-2.0
	 *	Unless required by applicable law or agreed to in writing, software
	 *	distributed under the License is distributed on an "AS IS" BASIS,
	 *	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	 *	See the License for the specific language governing permissions and
	 *	limitations under the License.
	 */

    require_once($path."OpenSiteAdmin/scripts/classes/Ajax.php");
	//include all of the standard fields
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Checkbox.php");
    require_once($path."OpenSiteAdmin/scripts/classes/Fields/Date.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/ForeignKey.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Group.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Hidden.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Image.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Label.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Password.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/RadioButtons.php");
    require_once($path."OpenSiteAdmin/scripts/classes/Fields/Select.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/Text.php");
	require_once($path."OpenSiteAdmin/scripts/classes/Fields/TextArea.php");

	/**
	 * Handles form field display and pre-processing.
	 *
	 * Sets up a form field for display, handling field and error display.
	 * Performs pre-processing on a form field, preparing it for use in a database.
	 *
	 * @author John Oren
	 * @version 1.5 December 24, 2009
	 */
	abstract class Field {
		/**
		 * @static
		 * @var Next form field name generator.
		 */
		protected static $nextFieldName = 0;

        /** @var An optional object to display Ajax for the field. */
        protected $ajax;
        /** @var The name of the CSS ID for this field. */
        protected $cssID;
        /** @var The default value to use for this field if none is provided. */
        protected $default;
        /** @var Text of any error messages generated during field processing. */
		protected $errorText;
        /** @var The name of this fields form field. */
        protected $fieldName;
        /** @var The integer type of the form this field is part of. */
        protected $formType;
        /** @var True if this field should be used in a list. */
        protected $inList;
        /** @var True if no value was supplied for this field. */
        protected $isEmpty;
        /** @var True if this field is associated with a database key. */
        private $keyField;
        /** @var The name of the database field this form field corresponds to. */
        protected $name;
        /** @var The options and flags associated with this form field. */
        protected $options;
        /** @var True if this field must be filled in. */
        protected $required;
        /** @var If true, no error messages will be displayed. */
        protected $silent;
		/** @var The title to display for this form field. */
		protected $title;
        /** @var The current value of this field. */
        protected $value;

		/**
		 * Constructs a form field with information on how to display it.
		 *
		 * @param STRING $name The name of the database field this form field corresponds to.
		 * @param STRING $title The title to display for this form field.
		 * @param MIXED $options The options associated with this form field.
		 * @param BOOLEAN $inList True if this field should be used in a list view.
		 * @param BOOLEAN $required True if this form field is required.
		 */
		function __construct($name, $title, $options, $inList, $required=false) {
			$this->name = $name;
			$this->title = $title;
			$this->options = $options;
			$this->inList = $inList;
			$this->required = $required;
            $this->fieldName = "field".Field::$nextFieldName++;
            $this->isEmpty = false;
            $this->silent = false;
            $this->keyField = false;
            $this->value = null;
            $this->cssID =$this->fieldName;
            $this->ajax = null;
        }

        /**
         * Sets the Ajax object associated with this field.
         *
         * @param OBJECT Ajax object
         * @return VOID
         */
        function addAjax(Ajax $ajax) {
            $ajax->setFieldName($this->getFieldName());
            $this->ajax = $ajax;
        }

        /**
         * Hack to allow foreign keys to process correctly if they are called
         * before their associated key gets processed.
         *
         * @return BOOLEAN False if unsuccessful
         */
		function databasePrep() {}

        /**
		 * Prepares a form field for display.
		 *
		 * @return STRING HTML to display for the form field
		 */
		abstract function display();

        /**
         * Returns the name of the CSS ID for this field.
         *
         * @return STRING
         */
        protected function getCSSID() {
            return $this->cssID;
        }

        /**
         * Returns any error text associated with this field.
         *
         * @return STRING Error messages.
         */
		protected function getErrorText() {
			if(empty($this->errorText) || $this->isSilent()) {
				return "";
			}
			return '<br><font color="red">'.$this->errorText.'</font>';
		}

        /**
		 * Returns the name of this field for use in an html form.
		 *
		 * @return STRING form field name.
		 */
		function getFieldName() {
			return $this->fieldName;
		}

        /**
		 * Returns the contents of this field for display in a list.
		 *
		 * @param STRING The default value to use for this field in a list.
		 * @return STRING The default value.
		 */
		function getListView($default) {
			return $default;
		}

		/**
		 * Returns the name of the SQL field this form field is associated with.
		 *
		 * @return STRING The name of this form field's corresponding SQL field.
		 */
		function getName() {
			return $this->name;
        }

        /**
		 * Returns the options and flags for this form field.
		 *
		 * @return MIXED Options for this form field.
		 */
		protected function getOptions() {
			return $this->options;
		}

        /**
		 * Returns the display name (title) of this form field.
         *
         * @param BOOLEAN $isList Excludes the visual queue for required fields in list view
		 * @return STRING The name to display with this form field.
		 */
		function getTitle($isList=false) {
			if(!$this->isRequired() || $isList) {
				return $this->title;
			} else {
				return "*".$this->title;
			}
        }

        /**
		 * Gets the current value of this form field.
         *
		 * @return MIXED Current field value.
		 */
		function getValue() {
			$ret = $this->value;
            if(empty($ret)) {
				return $this->default;
			}
			return $ret;
        }

        /**
		 * Returns true if the type of the form this field is in is a delete form.
		 *
		 * @return BOOLEAN True if this field is in a delete form.
		 */
		function isDelete() {
			return $this->formType == Form::DELETE;
		}

        /**
         * Returns true if this field does not have a value.
         *
         * @return BOOLEAN False if a value was provided.
         */
        function isEmpty() {
            return $this->isEmpty;
        }

        /**
		 * Returns whether or not this form field should be used in a list context.
		 *
		 * @return BOOLEAN True if this field should be displayed in a list view.
		 */
		function isInList() {
			return $this->inList;
        }

        /**
         * Returns whether or not this field is associated with a database key.
         *
         * @return BOOLEAN True if this field is associated with a database key.
         */
        function isKey() {
            return $this->keyField;
        }

		/**
		 * Returns whether or not this form field must be filled in.
		 *
		 * @return BOOLEAN True if this field cannot be empty.
		 */
		function isRequired() {
			return $this->required;
        }

        /**
         * Returns true if this field is currently suppressing error messages.
         *
         * @return BOOLEAN False if error messages are currently being displayed for this field.
         */
        function isSilent() {
            return $this->silent;
        }

        /**
         * Post-processes the field (sets the fields value internally and in the database).
         *
         * @param MIXED $value Value to store.
         * @return BOOLEAN False if any error were encountered.
         */
        protected function postProcess($value) {
            $this->setValue($value);
            return true;
        }

        /**
		 * Processes the field.
		 *
		 * @return BOOLEAN False if errors were encountered processing the field
		 */
		function process() {
			$this->value = $_POST[$this->getFieldName()];
            $this->setEmpty($this->value);
			if($this->isRequired() && $this->isEmpty()) {
				$this->errorText = "Please enter a value";
				return false;
			}

			return $this->postProcess($this->value);
		}

        /**
         * Sets the name of the CSS ID of this field.
         *
         * @param STRING The new CSS ID.
         * @return VOID
         */
        function setCSSID($cssID) {
            $this->cssID = $cssID;
        }

        /**
         * Sets the default value for this field.
         *
         * @param MIXED $value Default value
         * @return VOID
         */
		function setDefaultValue($value) {
			$this->default = $value;
        }

        /**
         * Internal function to evaluate if this field is empty and set the empty flag accordingly.
         *
         * @param MIXED $value Value (or values to check)
         * @return VOID
         */
        protected function setEmpty($value) {
            $this->isEmpty = empty($value);
        }

		/**
		 * Sets the form this field is in.
		 *
		 * @param INTEGER $formType A reference to the form this field is part of.
		 * @return VOID
		 */
		function setFormType($formType) {
			$this->formType = $formType;
        }

        /**
         * Makes this field a database key.
         *
         * @return VOID
         */
        function setKey() {
            $this->keyField = true;
        }

        /**
         * Toggles error message suppression for this field.
         *
         * @param BOOLEAN $silent True if errors should be suppressed.
         * @return VOID
         */
        function setSilent($silent) {
            $this->silent = $silent;
        }

        /**
		 * Sets the value of this field in the database.
         *
         * @param MIXED $value Value to set.
         * @return VOID
		 */
		function setValue($value) {
            $this->value = $value;
		}

        /**
         * Copies this field and gives it a new unique fieldname.
         *
         * @return VOID
         */
		function __clone() {
			$this->fieldName = "field".Field::$nextFieldName++;
		}
	}
?>
