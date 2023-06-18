<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');

const HELLOASSOV5_TOKENS_PATH  = __DIR__ . "/helloassoV5_tokens.json";

class HelloAssoConnector {
  public function __construct(){
    // An access token has a lifetime of 30 minutes so by refreshing it upon instantiation
    // we ensure the query will have a valid token whenever it needs it.
    // This logic is suboptimal in number of queries to get fresh tokens but:
    // - it leads to simpler code
    // - since our main endpoint is most of the time called only once per hour, it doesn't matter that much
    $this->ensureTokensOnDiskAreUpToDate();
  }

  private function ensureTokensOnDiskAreUpToDate(){
    // According to Helloasso doc:
    // > you MUST obtain a new access_token using the refresh_token issued to you,
    // > and MUST NOT obtain a new access_token by using the client
    if ($this->isValidTokensFile()){
      $this->refreshTokens();
    } else {
      $this->getTokensFromScratch();
    }
  }

  private function getTokensFromScratch() {
    global $loggerInstance;
    $loggerInstance->log_info("Going to get helloasso tokens from scratch");
    /**
     * For debugging purposes, to do a curl query from CLI:
     * curl -X POST 'https://api.helloasso.com/oauth2/token' -H 'content-type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=client_credentials' --data-urlencode 'client_id=$CLIENT_ID' --data-urlencode 'client_secret=$CLIENT_SECRET'
     */
    $raw_content = $this->doHAQueryToGetTokens([
      "grant_type" => "client_credentials",
      "client_id" => HA_CLIENT_ID,
      "client_secret" => HA_CLIENT_SECRET
    ])->response;
    $this->writeTokensFile($raw_content);
  }

  private function parseAccessToken(){
    $tokens = $this->parseTokensAsArray();
    return $tokens["access_token"];

  }

  private function parseRefreshToken(){
    $tokens = $this->parseTokensAsArray();
    return $tokens["refresh_token"];
  }

  private function parseTokensAsArray(){
    return json_decode(file_get_contents(HELLOASSOV5_TOKENS_PATH), true);
  }

  private function refreshTokens(){
    global $loggerInstance;
    /**
     * For debugging purposes, to do a curl query from CLI:
     * curl -X POST 'https://api.helloasso.com/oauth2/token' -H 'content-type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=refresh_token' --data-urlencode 'client_id=$CLIENT_ID' --data-urlencode 'refresh_token=$REFRESH_TOKEN'
     */
    $loggerInstance->log_info("Going to use helloasso refresh token");
    $response = $this->doHAQueryToGetTokens([
      "grant_type" => "refresh_token",
      "client_id" => HA_CLIENT_ID,
      "refresh_token" => $this->parseRefreshToken()
    ]);
    if ($response->httpCode == 401){
      $logger->log_info("Got 401 when trying to use helloasso refresh token. We try to get brand new ones");
      $this->getTokensFromScratch();
    } else {
      $loggerInstance->log_info("Got new helloasso tokens (http: " . $response->httpCode . ")");
      $this->writeTokensFile($response->response);
    }
  }

  private function doHAQueryToGetTokens($payload){
    $curl = curl_init("https://api.helloasso.com/oauth2/token");
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("content-type: application/x-www-form-urlencoded"));
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));

    return do_curl_query($curl);
  }

  private function writeTokensFile($content){
    global $loggerInstance;
    if (!self::isValidTokensJson($content)){
      $loggerInstance->log_error("received invalid tokens from helloasso so we don't overwrite the existing file. Received: $content");
      return;
    }

    if (!file_exists(dirname(HELLOASSOV5_TOKENS_PATH))) {
        mkdir(dirname(HELLOASSOV5_TOKENS_PATH), 0700, true);
    }
    file_put_contents(HELLOASSOV5_TOKENS_PATH, $content);
  }

  private function isValidTokensFile(){
    global $loggerInstance;
    if (!file_exists(HELLOASSOV5_TOKENS_PATH)){
      $loggerInstance->log_info("The tokens file doesn't exist");
      return false;
    }

    $content = file_get_contents(HELLOASSOV5_TOKENS_PATH);
    if (self::isValidTokensJson($content)){
      $loggerInstance->log_info("Local tokens file seems ok");
      return true;
    } else {
      $loggerInstance->log_error("Local tokens file seems not ok. We delete it. Was: $content");
      unlink(HELLOASSOV5_TOKENS_PATH);
      return false;
    }
  }

  public static function isValidTokensJson(string $content){
    global $loggerInstance;
    $tokens = json_decode($content, true);
    $reasonNotOk = "";
    if (is_null($tokens)){
      $loggerInstance->log_info("The content doesn't look like valid json");
      return false;
    } else if (!array_key_exists("access_token", $tokens)){
      $loggerInstance->log_info("The content is missing access_token");
      return false;
    } else if (!array_key_exists("refresh_token", $tokens)){
      $loggerInstance->log_info("The content is missing refresh_token");
      return false;
    }
    return true;
  }

  public function getAllHelloAssoSubscriptions(DateTime $from, DateTime $to){
    global $loggerInstance;

    $loggerInstance->log_info("Going to get HelloAsso registrations");
    $actions1 = $this->getAllHelloAssoSubscriptionsForOneCampaign($from, $to, HA_REGISTRATION_FORM_SLUG);
    $loggerInstance->log_info("Got " . count($actions1) . " registrations from " . HA_REGISTRATION_FORM_SLUG);

    $actions2 = array();
    if (!is_null(HA_REGISTRATION_FORM_SLUG_2)){
      $actions2 = $this->getAllHelloAssoSubscriptionsForOneCampaign($from, $to, HA_REGISTRATION_FORM_SLUG_2);
      $loggerInstance->log_info("Got " . count($actions2) . " registrations from " . HA_REGISTRATION_FORM_SLUG_2);
    }

    return array_merge($actions1, $actions2);
  }

  private function getAllHelloAssoSubscriptionsForOneCampaign(DateTime $from, DateTime $to, $formSlug){
    global $loggerInstance;
    $loggerInstance->log_info("Going to fetch data from $formSlug");
    $result = array();
    $json = $this->getHelloAssoJsonSubscriptionsForOneCampaign($from, $to, $formSlug);
    $dataKey = "data";
    if (!array_key_exists($dataKey, $json)){
      $loggerInstance->log_error("No $dataKey in the json. Got: " .print_r($json, TRUE));
      die();
    }
    $data = $json[$dataKey];
    foreach($data as $jsonRegistration){
      if ( $this->ismembership($jsonRegistration)){
        $result[] = $this->parseJsonRegistration($jsonRegistration);
      } else {
        $loggerInstance->log_info("skipping a line which isn't a membership");
      }
    }

    return $result;
  }

  private function isMembership($jsonRegistration){
    return $jsonRegistration["type"] === "Membership"; // we filter out for instance "Donation"
  }

  private function getHelloAssoJsonSubscriptionsForOneCampaign(DateTime $from, DateTime $to, $formSlug){
    global $loggerInstance;
    $accessToken = $this->parseAccessToken();
    $url = "https://api.helloasso.com/v5/organizations/"
      . HA_ORGANIZATION_SLUG
      . "/forms/Membership/$formSlug/items"
      . "?from=" . dateToStr($from)
      . "&to=" . dateToStr($to)
      . "&withDetails=true" // to get custom fields
      . "&retrieveAll=true"; // so we don't have to bother with pagination
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Bearer $accessToken"));

    $raw_content = do_curl_query($curl)->response;
    $json = json_decode($raw_content, true);
    if ( $json === NULL ){
      $loggerInstance->log_error("failed to parse: " . $raw_content);
      die("failed to parse: " . $raw_content);
    }
    $loggerInstance->log_info("Made request from " . dateToStr($from) . " to " . dateToStr($to));
    return $json;
  }

  private function parseJsonRegistration($jsonRegistration){
    global $loggerInstance;
    $result = new RegistrationEvent();
    $result->helloasso_event_id = $jsonRegistration["id"];
    $result->event_date = $jsonRegistration["order"]["date"];
    $result->first_name = $jsonRegistration["user"]["firstName"];
    $result->last_name = $jsonRegistration["user"]["lastName"];

    if (! array_key_exists("customFields", $jsonRegistration)){
        $loggerInstance->log_error("no 'customFields' in the registration " . print_r($jsonRegistration, true));
        die();
    }
    foreach($jsonRegistration["customFields"] as $customField){
      switch($customField["name"]){
        case "Email":
          $result->email = $customField["answer"];
          break;
        case "Ville":
          $result->city = $customField["answer"];
          break;
        case "Code Postal":
          $result->postal_code = $customField["answer"];
          break;
        case "Date de naissance":
        case "Numéro de téléphone":
           $result->phone = $customField["answer"];
           break;
        case "Comment as-tu connu Zero Waste Paris ?":
           $result->how_did_you_know_zwp = $customField["answer"];
           break;
        case "Sur quel projet souhaites-tu t'investir en priorité ?":
           $result->want_to_do = $customField["answer"];
           break;
        case "Portes-tu un projet professionnel autour du zéro déchet ?":
           $result->is_zw_professional = $customField["answer"];
           break;
      }
    }

    return $result;
  }
}
