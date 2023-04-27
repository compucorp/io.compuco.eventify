<?php

namespace Civi\Eventify\Hook\Post;

use CRM_Eventify_SettingsManager as SettingsManager;

class EventifySync {

  /**
   * @var mixed
   */
  private $participant;
  /**
   * @var mixed
   */
  private $event;
  /**
   * @var mixed
   */
  private $contact;
  /**
   * @var string
   */
  private $eventifyEventIdField;
  /**
   * @var string
   */
  private $participantRolesToSyncField;
  /**
   * @var string
   */
  private $syncStatusField;
  /**
   * @var string
   */
  private $syncMessageField;
  /**
   * @var string
   */
  private $apiResponseField;

  /**
   * @var string
   */
  private $generatedToken;
  /**
   * @var string
   */
  private $eventifyEventID;
  /**
   * @var string
   */
  private $organizationField;

  /**
   * EventifySync constructor.
   * @param $objectId
   * @throws CiviCRM_API3_Exception
   */
  public function __construct($objectId) {
    $this->participant = civicrm_api3('Participant', 'get', [
      'sequential' => 1,
      'id' => $objectId,
      'api.Event.get' => [],
      'api.Contact.get' => [],
    ])['values'][0];
    $this->event = $this->participant['api.Event.get']['values'][0];
    $this->contact = $this->participant['api.Contact.get']['values'][0];
    $this->setEventCustomFields();
    $this->setParticipantCustomFields();
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  private function setEventCustomFields() {
    $fields = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => "eventify_integration_event",
    ])['values'];
    foreach ($fields as $field) {
      switch ($field) {
        case $field['name'] == 'eventify_event_id':
          $this->eventifyEventIdField = 'custom_' . $field['id'];
          break;

        case $field['name'] == 'participant_roles_to_sync':
          $this->participantRolesToSyncField = 'custom_' . $field['id'];
          break;

      }
    }
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  private function setParticipantCustomFields() {
    $fields = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => "eventify_integration_participant",
    ])['values'];
    foreach ($fields as $field) {
      switch ($field) {
        case $field['name'] == 'sync_status':
          $this->syncStatusField = 'custom_' . $field['id'];
          break;

        case $field['name'] == 'sync_message':
          $this->syncMessageField = 'custom_' . $field['id'];
          break;

        case $field['name'] == 'api_response':
          $this->apiResponseField = 'custom_' . $field['id'];
          break;
      }
    }
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function sync() {
    $eventifyEventID = $this->event[$this->eventifyEventIdField];
    if (empty($eventifyEventID)) {
      return;
    }
    if (!$this->shouldSync()) {
      return;
    }

    $this->eventifyEventID = $eventifyEventID;

    try {
      $this->generatedToken = $this->generateToken($this->eventifyEventID);

      if (is_null($this->generatedToken)) {
        return;
      }

      //When the participant is registered via Webform or online registration form
      //the custom fields will not passed if the fields are not created in a profile / forms
      //therefore, custom fields will not exist here so $syncStatus should be assigned as empty
      if (!array_key_exists($this->syncStatusField, $this->participant)) {
        $syncStatus = '';
      }
      else {
        $syncStatus = $this->participant[$this->syncStatusField];
      }

      if ($syncStatus != 'update' && $syncStatus != '') {
        return;
      }

      $this->syncParticipant();
    }
    catch (\Exception $exception) {
      \Civi::log()->error($exception->getMessage());
      \Civi::log()->error($exception->getTraceAsString());
    }
  }

  private function getCountryByID($id) {
    if (empty($id)) {
      $id = civicrm_api3('Setting', 'get', [
        'sequential' => 1,
        'return' => ["defaultContactCountry"],
      ])['values'][0]['defaultContactCountry'];
    }

    return civicrm_api3('Country', 'get', [
      'sequential' => 1,
      'id' => $id,
    ])['values'][0]['name'];
  }

  private function shouldSync() {
    $rolesToSync = $this->event[$this->participantRolesToSyncField];

    if (empty($rolesToSync)) {
      return TRUE;
    }

    $participantRoles = $this->participant['participant_role_id'];
    $hasRolesToSync = FALSE;
    if (!is_array($participantRoles) && in_array($participantRoles, $rolesToSync)) {
      $hasRolesToSync = TRUE;
    }
    else {
      foreach ($participantRoles as $role) {
        if (in_array($role, $rolesToSync)) {
          $hasRolesToSync = TRUE;
          break;
        }
      }
    }

    if (!$hasRolesToSync) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @throws \Exception
   */
  private function syncParticipant() {
    $attendeeFormNumber = $this->getAttendeeFormNumber();
    $userCategory = $this->getEventsUserCategory();
    $userInterest = $this->getEventsInterest();

    $settings = $this->getSettings();
    $url = $settings[SettingsManager::API_URL] . '/attendee';
    $header = $this->getHeader();

    $detailsForm = [
      ['id' => 'country', 'value' => $this->getCountryByID($this->contact['country_id'])],
    ];

    if ($this->organizationField) {
      $detailsForm[] = ['id' => 'organization', 'value' => $this->organizationField];
    }

    $data = [
      'eventid' => $this->eventifyEventID,
      'email' => $this->getPrimaryEmailAddressByContactId($this->contact['id']),
      'firstname' => $this->contact['first_name'],
      'lastname' => $this->contact['last_name'],
      'userpassword' => strval(rand(100000, 999999)),
      'form_number ' => $attendeeFormNumber,
      'isactive' => 1,
      'isadmin' => 0,
      'isnetworking' => 1,
      'profile_url' => NULL,
      'details_form' => $detailsForm,
      'exhibitoridfk' => NULL,
      'speakeridfk' => NULL,
      'sponsoridfk' => NULL,
      'usercategory' => strval($userCategory),
      'userinterests' => strval($userInterest),
      'userwaitlistmode' => 0,
    ];

    list($code, $response) = $this->callAPI($url, $header, $data);
    $this->handleIfApiReturnsError($code, $response);
    $this->updateParticipantSyncStatus($code, $response);
  }

  /**
   * @param $contactId
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function getPrimaryEmailAddressByContactId($contactId) {
    return civicrm_api3('Email', 'getsingle', [
      'sequential' => 1,
      'contact_id' => $contactId,
      'is_primary' => 1,
    ])['email'];
  }

  /**
   * @throws \Exception
   */
  private function getAttendeeFormNumber() {
    $settings = $this->getSettings();
    $url = $settings[SettingsManager::API_URL] . '/myForms' . '?eventid=' . $this->eventifyEventID;
    $header = $this->getHeader();
    list($code, $response)  = $this->callAPI($url, $header, NULL, 'GET');
    $this->handleIfApiReturnsError($code, $response);

    return $response['attendee']['form_number'];
  }

  /**
   * @throws \Exception
   */
  private function getEventsUserCategory() {
    $settings = $this->getSettings();
    $url = $settings[SettingsManager::API_URL] . '/events/user_categories' . '?eventid=' . $this->eventifyEventID;
    $header = $this->getHeader();
    list($code, $response)  = $this->callAPI($url, $header, NULL, 'GET');

    $this->handleIfApiReturnsError($code, $response);

    foreach ($response as $item) {
      if ($item['category_type'] == 'attendee') {
        return $item['category_id'];
      }
    }

    return NULL;
  }

  /**
   * @throws \Exception
   */
  private function getEventsInterest() {
    $settings = $this->getSettings();
    $url = $settings[SettingsManager::API_URL] . '/events/interest' . '?eventid=' . $this->eventifyEventID;;
    $header = $this->getHeader();
    list($code, $response)  = $this->callAPI($url, $header, NULL, 'GET');
    $this->handleIfApiReturnsError($code, $response);

    foreach ($response as $item) {
      if ($item['interest'] == 'event') {
        return $item['interest_id'];
      }
    }

    return NULL;
  }

  /**
   * @throws \Exception
   */
  private function generateToken($eventID = NULL) {
    $settings = $this->getSettings();
    $url = $settings[SettingsManager::API_URL] . '/generatetoken';
    $data = [
      'email' => $settings[SettingsManager::EMAIL],
      'password' => $settings[SettingsManager::PASSWORD],
    ];

    if (!is_null($eventID)) {
      $data['eventid'] = $eventID;
    }

    $header = [
      'Accept:application/json',
      'Content-Type:application/json',
    ];

    list($code, $response) = $this->callAPI($url, $header, $data);
    if (empty($response['session_token'])) {
      $this->updateParticipantSyncStatus(400, $response);

      return NULL;
    }

    return $response['session_token'];
  }

  private function handleIfApiReturnsError($code, $response) {
    if (empty($response['error'])) {
      return;
    }

    if ($code == 200) {
      $errorCode = $response['error']['code'];
      $errorResponse = $response['error'];
    }
    else {
      $errorCode = $code;
      $errorResponse = $response;
    }

    $this->updateParticipantSyncStatus($errorCode, $errorResponse);
    throw new \Exception('There is an issue syncing participant ' . var_dump($response));
  }

  /**
   *
   * @param $httpStatus
   * @param $response
   * @throws CiviCRM_API3_Exception
   *
   */
  private function updateParticipantSyncStatus($httpStatus, $response) {
    switch ($httpStatus) {
      case 200:
      case 400:
        $syncStatus = $httpStatus;
        break;

      default:
        $syncStatus = 'other';
    }

    $participantParam = [
      'id' => $this->participant['id'],
      'event_id' => $this->event['id'],
      'contact_id' => $this->contact['id'],
      $this->syncStatusField => $syncStatus,
      $this->syncMessageField => $response['message'],
      $this->apiResponseField => json_encode($response),
    ];

    civicrm_api3('Participant', 'create', $participantParam);
  }

  private function getHeader() {
    return [
      'Accept: application/json',
      'Session-Token: ' . $this->generatedToken,
      'Content-Type: application/json',
    ];
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  private function callAPI($url, $header, $payload, $method = 'POST') {
    set_time_limit(60);
    $connection = curl_init();
    switch ($method) {
      case 'POST':
        curl_setopt($connection, CURLOPT_POST, TRUE);
        curl_setopt($connection, CURLOPT_POSTFIELDS, json_encode($payload));
        break;

      default:
        curl_setopt($connection, CURLOPT_CUSTOMREQUEST, $method);
        if (!is_null($payload)) {
          curl_setopt($connection, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    }
    curl_setopt_array($connection, [
      CURLOPT_URL            => $url,
      CURLOPT_HTTPHEADER     => $header,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($connection);
    $httpStatus = curl_getinfo($connection, CURLINFO_HTTP_CODE);
    curl_close($connection);

    return array_values([$httpStatus, json_decode($response, TRUE)]);
  }

  private function getSettings() {
    return SettingsManager::getSettingsValue();
  }

}
