<?php

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');

class HelloAssoConnector {

  private function jsonToMessage($json){
    $result = new RegistrationEvent();
    $result->helloasso_event_id = $json["id"];
    $result->event_date = $json["date"];
    $result->amount = $json["amount"];
    $result->first_name = $json["first_name"];
    $result->last_name = $json["last_name"];

    foreach($json["custom_infos"] as $custom_info){
      switch($custom_info["label"]){
        case "Email":
           // Don't use $json["email"] because it may not be the correct one when several people registered at once
           $result->email = $custom_info["value"];
           break;
        case "Adresse":
           // HelloAsso native label for this field. Shouldn't be changed in HelloAsso
           $result->address = $custom_info["value"];
           break;
        case "Ville":
           $result->city = $custom_info["value"];
           break;
        case "Code Postal":
           // HelloAsso native label for this field. Shouldn't be changed in HelloAsso.
           $result->postal_code = $custom_info["value"];
           break;
        case "Date de naissance":
           $result->birth_date = $custom_info["value"];
           break; 
        case "Déjà adhérent à Zero Waste France?": //Legacy field. Not used  starting in 2021
          $result->is_zwf_adherent = $custom_info["value"];
           break;
        case "Numéro de téléphone":
           $result->phone = $custom_info["value"];
           break;
        case "Comment as-tu connu Zero Waste Paris ?": //beware of the trailing whitespace
           $result->how_did_you_know_zwp = $custom_info["value"];
           break;
        case "Sur quels projets souhaites-tu t'investir ?":
           $result->want_to_do = $custom_info["value"];
           break;
        case "Es-tu déjà adhérent⋅e à Zero Waste France ?":
           $result->is_zwf_adherent = $custom_info["value"];
           break;
        case "Es-tu bénévole à la Maison du Zéro Déchet ?":
           $result->is_mzd_volunteer = $custom_info["value"];
           break;
        case "Portes-tu un projet professionnel autour du zéro déchet ?":
           $result->is_zw_professional = $custom_info["value"];
           break;
        case "Si tu étais déjà adhérent⋅e l'an dernier, quand as-tu rejoint l'asso pour la première fois ?":
           $result->is_already_member_since = $custom_info["value"];
           break;
        case "Devenir Membre Actif bénévole pour participer aux activités sur le terrain (Accès aux outils interne de communication)":
           $result->want_to_be_volunteer = $custom_info["value"];
           break;   
      }
    }

    // legacy field. Always set to "Oui" until we remove it from the schemas of our storages
    $result->want_to_be_volunteer = "Oui";

    return $result;
  }

  /**
   * For debugging purposes, to do a curl query to helloAsso:
   * curl -H "Authorization: Basic $AUTHENTICATION_TOKEN" "https://api.helloasso.com/v3/campaigns/$campaignId/payments.json?from=2018-07-10T00:00:00&to=2018-07-13T00:00:00"
   */

  private function getOnePageOfResults(DateTime $from, DateTime $to, $page, $campaignId){
    global $loggerInstance;
    $loggerInstance->log_info("Going to query HelloAsso from " . dateToStr($from) . " to " . dateToStr($to) . " for page $page");
    $curl = curl_init("https://api.helloasso.com/v3/campaigns/" . $campaignId . "/actions.json?from=" . dateToStr($from) . "&to=" . datetoStr($to) . "&page=" . $page);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Basic " . HA_AUTHENTICATION_TOKEN));
    $raw_content = do_curl_query($curl)->response;
    $json = json_decode($raw_content, true);
    if ( $json === NULL ){
      $loggerInstance->log_error("failed to parse: " . $raw_content);
      die("failed to parse: " . $raw_content);
    }
    $loggerInstance->log_info("Made request from " . dateToStr($from) . " to " . dateToStr($to) . " for page $page");
    return $json;
  }

  function getAllHelloAssoSubscriptions(DateTime $from, DateTime $to){
    global $loggerInstance;

    $loggerInstance->log_info("Going to get HelloAsso registrations");
    $actions = $this->getAllHelloAssoSubscriptionsForOneCampaign($from, $to, HA_REGISTRATIONS_CAMPAIGN_ID);

    $loggerInstance->log_info("Got " . count($actions) . " registrations");
    return $actions;
  }

  private function getAllHelloAssoSubscriptionsForOneCampaign(DateTime $from, DateTime $to, $campaignId){
    global $loggerInstance;
    $allActions = array();
    $reachedLastPage = false;
    $page = 1;

    while( ! $reachedLastPage ){
      $json = $this->getOnePageOfResults($from, $to, $page, $campaignId);
      $resourcesKey = "resources";
      if (!array_key_exists($resourcesKey, $json)){
        $loggerInstance->log_error("No resources in the json. Got: " .print_r($json, TRUE));
        die();
      }
      $actionsForPage = $json[$resourcesKey];
      foreach($actionsForPage as $action){
        if ($action["type"] != "SUBSCRIPTION") {
          $loggerInstance->log_error("Type d'action inattendu: " . $action["type"]);
        } else {
          $allActions[] = $this->jsonToMessage($action);
        }
      }

      if ( $page >= $json['pagination']['max_page'] ){ // if there are no result then max_page is 0, so checking for equality ends up in a infinite loop
        $reachedLastPage = true;
      }
      $page++;
    }

    return $allActions;
  }

}
