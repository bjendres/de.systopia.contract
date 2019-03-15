<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * A wrapper around Membership.create with appropriate fields passed.
 * You cannot schedule Contract.create for the future.
 */
function _civicrm_api3_Contract_create_spec(&$params) {
  include_once 'api/v3/Membership.php';
  _civicrm_api3_membership_create_spec($params);
}

/**
 * A wrapper around Membership.create with appropriate fields passed.
 * You cannot schedule Contract.create for the future.
 */
function civicrm_api3_Contract_create($params){

    // Any parameters with a period in will be converted to the custom_N format
    // Other fields will be passed directly to the membership.create API
    foreach ($params as $key => $value){
      if(strpos($key, '.')){
        unset($params[$key]);
        $params[CRM_Contract_Utils::getCustomFieldId($key)] = $value;
      }
    }
    $membership = civicrm_api3('Membership', 'create', $params);

    // link SEPA Mandate
    $recurring_contribution_field_key = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');
    if (!empty($params[$recurring_contribution_field_key])) {
      // link recurring contribution to contract
      CRM_Contract_SepaLogic::setContractPaymentLink($membership['id'], $params[$recurring_contribution_field_key]);
    }

    // update the generated activity
    $activity = civicrm_api3('Activity', 'getsingle', [
      'source_record_id' => $membership['id'],
      'activity_type_id' => 'Contract_Signed',
    ]);
    $activity = civicrm_api3('Activity', 'create', [
      'id'                 => $activity['id'],
      'details'            => $params['note'],
      'activity_date_time' => date('YmdHi00'),
      'medium_id'          => $params['medium_id'],
      'campaign_id'        => CRM_Utils_Array::value('campaign_id', $params),
    ]);
    return $membership;
}

