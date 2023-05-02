<?php

require_once 'eventify.civix.php';
// phpcs:disable
use CRM_Eventify_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function eventify_civicrm_config(&$config) {
  _eventify_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function eventify_civicrm_xmlMenu(&$files) {
  _eventify_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function eventify_civicrm_install() {
  _eventify_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function eventify_civicrm_postInstall() {
  _eventify_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function eventify_civicrm_uninstall() {
  _eventify_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function eventify_civicrm_enable() {
  _eventify_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function eventify_civicrm_disable() {
  _eventify_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function eventify_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _eventify_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function eventify_civicrm_entityTypes(&$entityTypes) {
  _eventify_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function eventify_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _eventify_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function eventify_civicrm_navigationMenu(&$menu) {
  _eventify_civix_insert_navigation_menu($menu, 'Administer/CiviEvent', [
    'label' => E::ts('Eventify Integration Settings'),
    'name' => 'eventify_integration_settings',
    'url' => 'civicrm/admin/setting/preferences/event/eventify',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _eventify_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_post().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_post
 */
function eventify_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName != 'Participant' || ($op == 'delete' || $op == 'view')) {
    return;
  }
  if (CRM_Core_Transaction::isActive()) {
    CRM_Core_Transaction::addCallback(
      CRM_Core_Transaction::PHASE_POST_COMMIT,
      'eventify_civicrm_post_callback', [$objectId]
    );
  }
  else {
    eventify_civicrm_post_callback($objectId);
  }
}

/**
 * @param $objectId
 */
function eventify_civicrm_post_callback($objectId) {
  $participantHook = new Civi\Eventify\Hook\Post\EventifySync($objectId);
  $participantHook->sync();
}

/**
 * Implements hook_civicrm_custom().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_custom/
 */
function eventify_civicrm_custom($op, $groupID, $entityID, &$params) {
  if ($op != 'create' && $op != 'edit') {
    return;
  }

  $hook = new Civi\Eventify\Hook\Custom\ParticipantCustomSync($op, $groupID, $entityID, $params);
  $hook->sync();
}
