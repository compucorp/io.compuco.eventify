<?php

namespace Civi\Eventify\Hook\Custom;

use Civi\Eventify\Hook\AbstractEventifySync;
use CRM_Eventify_SettingsManager as SettingsManager;

class ParticipantCustomSync extends AbstractEventifySync {

  private $op;
  private $groupID;
  private $entityID;
  private $params;

  public function __construct($op, $groupID, $entityID, &$params) {
    $this->op = $op;
    $this->groupID = $groupID;
    $this->entityID = $entityID;
    $this->params = &$params;
  }

  public function sync() {
    if (!$this->shouldSync()) {
      return;
    }

    $cacheName = 'eventify_sync_params_' . $this->entityID;
    $cache = \Civi::cache()->get($cacheName);
    $detailsForm = $cache['details_form'];
    $form = [];
    foreach ($detailsForm as $key => $field) {
      $form[$key]['id'] = $field['id'];
      if ($field['id'] == 'country') {
        $form[$key]['value'] = $this->getCountry();
        continue;
      }
      if ($field['id'] == 'company') {
        $form[$key]['value'] = $this->getOrganization();
        continue;
      }

      if (!empty($field['max_length'])) {
        $form[$key]['value'] = "";
        continue;
      }

      $form[$key]['value'] = FALSE;
    }

    $settings = $this->getSettings();
    $url = $settings[SettingsManager::API_URL] . '/attendee' . '/' . $cache['appuserid'];
    $header = [
      'Accept: application/json',
      'Session-Token: ' . $cache['session_token'],
      'Content-Type: application/json',
    ];

    $payload = [
      'eventid' => $cache['eventid'],
      'form_number' => $cache['form_number'],
      'details_form' => $form,
    ];

    $this->callAPI($url, $header, $payload, 'PATCH');
    \Civi::cache()->delete($cacheName);
  }

  private function shouldSync() {
    // WHF specific group (participant details)
    if ($this->groupID != 37) {
      return FALSE;
    }

    return TRUE;
  }

  private function getCountry() {
    $country = civicrm_api3('Participant', 'get', [
      'sequential' => 1,
      'id' => $this->entityID,
      'api.Contact.get' => [],
    ])['values'][0]['api.Contact.get']['values'][0]['country'];

    if (empty($country)) {
      return civicrm_api3('Setting', 'get', [
        'sequential' => 1,
        'return' => ["defaultContactCountry"],
      ])['values'][0]['defaultContactCountry'];
    }

    return $country;
  }

  private function getOrganization() {
    foreach ($this->params as $param) {
      if ($param['column_name'] == 'organization_represented_247') {
        return $param['value'];
      }
    }

    return NULL;
  }

}
