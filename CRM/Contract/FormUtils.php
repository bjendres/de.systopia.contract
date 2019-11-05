<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_FormUtils
{
    public function __construct($form, $entity)
    {
        // The form object and type of entity that we are updating with the form
        // is passed via the constructor
        $this->entity = $entity;
        $this->form = $form;
        $this->recurringContribution = new CRM_Contract_RecurringContribution;

    }

    public function addPaymentContractSelect2($elementName, $contactId, $required = true, $contractId)
    {
        $recurringContributionOptions[''] = '- none -';
        foreach($this->recurringContribution->getAll($contactId, true, $contractId) as $key => $rc){
          $recurringContributionOptions[$key] = $rc['label'];
        }
        $this->form->add('select', $elementName, ts('Mandate / Recurring Contribution'), $recurringContributionOptions, $required, array('class' => 'crm-select2 huge'));
    }

    public function replaceIdWithLabel($name, $entity)
    {
        list($groupName, $fieldName) = explode('.', $name);
        $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => $groupName, 'name' => $fieldName));

        $id = CRM_Contract_Utils::getCustomFieldId($name);

        // Get the custom data that was sent to the template
        $details = $this->form->get_template_vars('viewCustomData');

        // We need to know the id for the row of the custom group table that
        // this custom data is stored in
        if (isset($details[$result['custom_group_id']])) {
          $customGroupTableId = key($details[$result['custom_group_id']]);
          if (!empty($details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'])) {
            $entityId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
            if($entity == 'ContributionRecur'){
              try {
                $entityResult = civicrm_api3($entity, 'getsingle', array('id' => $entityId));
                $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = $this->recurringContribution->writePaymentContractLabel($entityResult);
              } catch (Exception $e) {
                $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = ts("NOT FOUND!");
              }
            }elseif($entity == 'BankAccountReference'){
              $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = CRM_Contract_BankingLogic::getIBANforBankAccount($entityId);
            }elseif($entity == 'PaymentInstrument'){
              $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'value' => $entityId, 'option_group_id' => "payment_instrument" ]);
            }
            // Write nice text and return this to the template
            $this->form->assign('viewCustomData', $details);
          }
        }
    }

    /**
     * The currency for custom fields always defaults to the default currency.
     * This converts the membership_payment.membership_annual field to string
     * and manually formats it with the currency obtained from the recurring
     * contribution.
     *
     * @throws \CiviCRM_API3_Exception
     */
    public function setPaymentAmountCurrency() {
      $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
      $details = $this->form->get_template_vars('viewCustomData');
      $customGroupTableId = key($details[$result['custom_group_id']]);
      $recContributionId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
      $recContribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $recContributionId));
      $customGroupTableId = key($details[$result['custom_group_id']]);
      $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_annual'));
      $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = CRM_Utils_Money::format($details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'], $recContribution['currency']);
      $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_data_type'] = 'String';
      $this->form->assign('viewCustomData', $details);
    }

    public function showMembershipTypeLabel()
    {
        $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'contract_updates', 'name' => 'ch_membership_type'));
        // Get the custom data that was sent to the template
        $details = $this->form->get_template_vars('viewCustomData');

        // We need to know the id for the row of the custom group table that
        // this custom data is stored in
        if (!empty($details[$result['custom_group_id']])) {
          $customGroupTableId = key($details[$result['custom_group_id']]);

          $membershipTypeId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
          if($membershipTypeId){
              $membershipType = civicrm_api3('MembershipType', 'getsingle', ['id' => $membershipTypeId]);

              // Write nice text and return this to the template
              $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = $membershipType['name'];
              $this->form->assign('viewCustomData', $details);
          }
        }
    }

    public function removeMembershipEditDisallowedCoreFields()
    {
        foreach ($this->getMembershpEditDisallowedCoreFields() as $element) {
            if ($this->form->elementExists($element)) {
                $this->form->removeElement($element);
            }
        }
    }

    public function getMembershpEditDisallowedCoreFields()
    {
        return array('status_id', 'is_override');
    }

    public function removeMembershipEditDisallowedCustomFields()
    {
        // $customGroupsToRemove = array('membership_cancellation');
        $customGroupsToRemove = array();
        $customFieldsToRemove['membership_payment'] = array();

        foreach ($this->form->_groupTree as $groupKey => $group) {
            if (in_array($group['name'], $customGroupsToRemove)) {
                unset($this->form->_groupTree[$groupKey]);
            } else {
                foreach ($group['fields'] as $fieldKey => $field) {
                    if (isset($customFieldsToRemove[$group['name']]) && in_array($field['column_name'], $customFieldsToRemove[$group['name']])) {
                        unset($this->form->_groupTree[$groupKey]['fields'][$fieldKey]);
                    }
                }
            }
        }
    }

    /**
     * Add a download link to the custom field membership_contract
     * @param $membershipId
     */
    public function addMembershipContractFileDownloadLink($membershipId) {
      $membershipContractCustomField = civicrm_api3('CustomField', 'getsingle', array('name' => "membership_contract", 'return' => "id"));
      $membership = civicrm_api3('Membership','getsingle', array('id' => $membershipId));
      if (!empty($membership['custom_'.$membershipContractCustomField['id']])) {
        $membershipContract = $membership['custom_'.$membershipContractCustomField['id']];
        $contractFile = CRM_Contract_Utils::contractFileExists($membershipContract);
        if ($contractFile) {
          $script = file_get_contents(CRM_Core_Resources::singleton()->getUrl('de.systopia.contract', 'templates/CRM/Member/Form/MembershipView.js'));
          $url = CRM_Utils_System::url('civicrm/contract/download', "contract=".urlencode($membershipContract));
          $script = str_replace('CONTRACT_FILE_DOWNLOAD', $url, $script);
          CRM_Core_Region::instance('page-footer')->add(array(
            'script' => $script,
            'name'   => 'contract-download@de.systopia.contract',
          ));
        }
      }
    }

    /**
     * Called from civicrm/contract/download?contract={contract_id}
     * This function downloads a contract file via the users web browser
     */
    public function downloadMembershipContract() {
      // If we requested a contract file download
      $download = CRM_Utils_Request::retrieve('contract', 'String', CRM_Core_DAO::$_nullObject, FALSE, '', 'GET');
      if (!empty($download)) {
        // FIXME: Could use CRM_Utils_System::download but it still requires you to do all the work (load file to stream etc) before calling.
        if (CRM_Contract_Utils::downloadContractFile($download)) {
          CRM_Utils_System::civiExit();
        }
        // If the file didn't exist
        echo "File does not exist";
        CRM_Utils_System::civiExit();
      }
    }
}
