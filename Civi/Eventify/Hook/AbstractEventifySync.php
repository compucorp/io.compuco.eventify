<?php

namespace Civi\Eventify\Hook;

use CRM_Eventify_SettingsManager as SettingsManager;

abstract class AbstractEventifySync {

  protected function getSettings() {
    return SettingsManager::getSettingsValue();
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  protected function callAPI($url, $header, $payload, $method = 'POST') {
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

}
