<?php


use CRM_Eventify_SettingsManager as SettingsManager;

/*
 * Settings metadata file
 */
return [
  SettingsManager::API_URL => [
    'group_name' => SettingsManager::GROUP_NAME,
    'group' => SettingsManager::GROUP,
    'name' => SettingsManager::API_URL,
    'title' => 'API URL',
    'type' => 'String',
    'html_type' => 'text',
    'quick_form_type' => 'Element',
    'default' => '',
    'is_help' => FALSE,
    'is_required' => TRUE,
    'html_attributes' => ['size' => 50],
    'extra_data' => '',
  ],
  SettingsManager::EMAIL => [
    'group_name' => SettingsManager::GROUP_NAME,
    'group' => SettingsManager::GROUP,
    'name' => SettingsManager::EMAIL,
    'title' => 'Email',
    'type' => 'String',
    'html_type' => 'text',
    'quick_form_type' => 'Element',
    'default' => '',
    'is_help' => FALSE,
    'is_required' => TRUE,
    'html_attributes' => ['size' => 50],
    'extra_data' => '',
  ],
  SettingsManager::PASSWORD => [
    'group_name' => SettingsManager::GROUP_NAME,
    'group' => SettingsManager::GROUP,
    'name' => SettingsManager::PASSWORD,
    'title' => 'Password',
    'type' => 'String',
    'html_type' => 'password',
    'quick_form_type' => 'Element',
    'default' => '',
    'is_help' => FALSE,
    'is_required' => TRUE,
    'html_attributes' => ['size' => 50],
    'extra_data' => '',
  ],
];
