<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Contract validation functions
 */
class CRM_Contract_Validation_ContractNumber {

  /**
   * Verifies that a membership_general.membership_contract number is UNIQUE
   * (unless its part of the exceptions, or already set for this ID)
   *
   * @param $reference   string  proposed reference
   * @param $contract_id int     updated contract, or empty if NEW
   *
   * @return NULL if given reference is valid, error message otherwise
   */
  public static function verifyContractNumber($reference, $contract_id = NULL) {
    // empty references are acceptable
    if (empty($reference)) {
      return NULL;
    }

    // check if part of the exceptions
    $exceptions = CRM_Contract_Configuration::getUniqueReferenceExceptions();
    if (in_array($reference, $exceptions)) {
      return NULL;
    }

    // Validate requested reference:
    // prepare query
    $query = array(
      'membership_general.membership_contract' => $reference,
      'return'                                 => 'id',
      'option.limit'                           => 1
    );
    CRM_Contract_CustomData::resolveCustomFields($query);

    if (empty($contract_id)) {
      // NEW CONTRACT is to be created:
      $usage = civicrm_api3('Membership', 'get', $query);
      if ($usage['count'] > 0) {
        return ts("Reference '%1' is already in use!", array(1 => $reference));
      } else {
        return NULL;
      }

    } else {
      // // EXISTING CONTRACT is being updated
      $query['id'] = $contract_id;
      $unchanged = civicrm_api3('Membership', 'getcount', $query);
      if ($unchanged) {
        // this means the reference is already used by this contract
        return NULL;
      }

      // see if the reference is used elsewhere
      $query['id'] = array('<>' => $contract_id);
      $is_used = civicrm_api3('Membership', 'getcount', $query);
      if ($is_used) {
        // this means the reference is already used
        return ts("Reference '%1' is already in use!", array(1 => $reference));
      } else {
        return NULL;
      }
    }
  }
}
