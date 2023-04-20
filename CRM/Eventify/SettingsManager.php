<?php

class CRM_Eventify_SettingsManager {

  /**
   * Constants for setting name
   */
  const GROUP_NAME = 'Eventify Integration Settings';
  const GROUP = 'eventify_integration';
  const EMAIL = 'email';
  const PASSWORD = 'password';
  const API_URL = 'api_url';

  /**
   * Gets the extension setting fields
   *
   * @return array
   */
  public static function getSettingFields() {
    $settingFields = self::fetchSettingFields();
    if (!isset($settingFields) || empty($settingFields)) {
      $result = civicrm_api3('System', 'flush');

      if ($result['is_error'] == 0) {
        $settingFields = self::fetchSettingFields();
      }
    }

    return $settingFields;
  }

  /**
   * Gets multiple settings values
   *
   * @param null $settings
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  public static function getSettingsValue($settings = NULL) {
    if ($settings == NULL) {
      $settingFields = self::getSettingFields();
      $settings = array_keys($settingFields);
    }
    return civicrm_api3('setting', 'get', [
      'return' => $settings,
      'sequential' => 1,
    ])['values'][0];
  }

  /**
   * Fetch Settings fields
   */
  private static function fetchSettingFields() {
    return civicrm_api3('setting', 'getfields', [
      'filters' => ['group' => self::GROUP],
    ])['values'];
  }

}
