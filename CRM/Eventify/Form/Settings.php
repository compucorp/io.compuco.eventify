<?php

use CRM_Eventify_ExtensionUtil as E;
use CRM_Eventify_SettingsManager as SettingsManager;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Eventify_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Eventify Integration Settings'));
    $settingFields = SettingsManager::getSettingFields();
    $this->generateFormElement($settingFields);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ]);
    $this->assign('elementNames', $this->getRenderableElementNames());

  }

  /**
   * Generate a default Setting field to the form.
   *
   * @param array $settings
   * @throws CRM_Core_Exception
   */
  private function generateFormElement($settings) {
    foreach ($settings as $setting) {
      $this->add(
        $setting['html_type'],
        $setting['name'],
        E::ts($setting['title']),
        $setting['html_attributes'],
        $setting['is_required']
      );
    }
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }

    return $elementNames;
  }

  public function postProcess() {
    parent::postProcess();
    $settingField = SettingsManager::getSettingFields();
    $submittedValues = $this->exportValues();

    $valuesToSave = array_intersect_key($submittedValues, $settingField);

    $result = civicrm_api3('setting', 'create', $valuesToSave);
    $session = CRM_Core_Session::singleton();
    if ($result['is_error'] == 0) {
      $session->setStatus(
        E::ts('Settings have been saved'),
        E::ts('Eventify Integration Settings'),
        'success'
      );
    }
    else {
      $session->setStatus(
        E::ts('Settings could not be saved, please contact Administrator'),
        E::ts('Eventify Integration Settings'),
        'error'
      );
    }
  }

  /**
   * Set defaults for form.
   *
   * @see CRM_Core_Form::setDefaultValues()
   */
  public function setDefaultValues() {
    $currentValues = civicrm_api3('setting', 'get', [
      'return' => array_keys(SettingsManager::getSettingFields()),
    ]
    );
    $domainID = CRM_Core_Config::domainID();
    $defaults = [];
    foreach ($currentValues['values'][$domainID] as $name => $value) {
      $defaults[$name] = $value;
    }

    return $defaults;
  }

}
