<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_Modify extends CRM_Core_Form{

  function preProcess(){

    // If we requested a contract file download
    $download = CRM_Utils_Request::retrieve('ct_dl', 'String', CRM_Core_DAO::$_nullObject, FALSE, '', 'GET');
    if (!empty($download)) {
      // FIXME: Could use CRM_Utils_System::download but it still requires you to do all the work (load file to stream etc) before calling.
      if (CRM_Contract_Utils::downloadContractFile($download)) {
        CRM_Utils_System::civiExit();
      }
      // If the file didn't exist
      echo "File does not exist";
      CRM_Utils_System::civiExit();
    }

    // Not sure why this isn't simpler but here is my way of ensuring that the
    // id parameter is available throughout this forms life
    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    if($this->id){
      $this->set('id', $this->id);
    }
    if(!$this->get('id')){
      CRM_Core_Error::fatal('Missing the contract ID');
    }

    // Load the the contract to populate default form values
    try{
      $this->membership = civicrm_api3('Membership', 'getsingle', ['id' => $this->get('id')]);
    }catch(Exception $e){
      CRM_Core_Error::fatal('Not a valid contract ID');
    }

    // Load the modificationActivity
    $this->modify_action = CRM_Utils_Request::retrieve('modify_action', 'String');
    if($this->modify_action){
      $this->set('modify_action', $this->modify_action);
    }
    $modificationActivityClass = 'CRM_Contract_ModificationActivity_'.ucfirst($this->get('modify_action'));
    $this->modificationActivity = new $modificationActivityClass;
    $this->assign('modificationActivity', $this->modificationActivity->getAction());

    // Set the form title (based on the update action)
    CRM_Utils_System::setTitle(ucfirst($this->modificationActivity->getAction()).' contract');

    // Set the destination for the form
    $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$this->membership['contact_id']}&selectedChild=member");

    // Assign the contact id (necessary for the mandate popup)
    $this->assign('cid', $this->membership['contact_id']);

    // Validate that the contract has a valid start status
    $this->membershipStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->membership['status_id']));
    if(!in_array($this->membershipStatus['name'], $this->modificationActivity->getStartStatuses())){
      CRM_Core_Error::fatal("You cannot {$this->modificationActivity->getAction()} a membership when its status is '{$this->membershipStatus['name']}'.");
    }
  }

  function loadEntities(){


  }

  function buildQuickForm(){

    // Add fields that are present on all contact history forms

    // Add the date that this update should take effect (leave blank for now)
    $this->addDate('activity_date', ts('Schedule date'), TRUE);

    // Add the interaction medium
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id"])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', ts('Source media'), array('' => '- none -') + $mediumOptions, false, array('class' => 'crm-select2'));

    // Add a note field
    $this->addWysiwyg('activity_details', ts('Notes'), []);

    // Then add fields that are dependent on the action
    if(in_array($this->modificationActivity->getAction(), array('update', 'revive'))){
      $this->addUpdateFields();
    }elseif($this->modificationActivity->getAction() == 'cancel'){
      $this->addCancelFields();
    }elseif($this->modificationActivity->getAction() == 'pause'){
      $this->addPauseFields();
    }

    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Discard changes'), // since Cancel looks bad when viewed next to the Cancel action
        array('type' => 'submit', 'name' => ucfirst($this->modificationActivity->getAction().' contract'), 'isDefault' => true)
    ));

    $this->setDefaults();
  }

  // Note: also used for revive
  function addUpdateFields(){

    // JS for the pop up
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Contract/Form/MandateBlock.js');
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $this->membership['contact_id']));

    $formUtils = new CRM_Contract_FormUtils($this, 'Membership');
    $formUtils->addPaymentContractSelect2('recurring_contribution', $this->membership['contact_id'], true);

    // Membership type (membership)
    foreach(civicrm_api3('MembershipType', 'get', [])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    };
    $this->add('select', 'membership_type_id', ts('Membership type'), array('' => '- none -') + $MembershipTypeOptions, true, array('class' => 'crm-select2'));

    // Campaign
    $this->addEntityRef('campaign_id', ts('Campaign'), [
      'entity' => 'campaign',
      'placeholder' => ts('- none -')
    ]);
  }

  function addCancelFields(){

    // Cancel reason
    foreach(civicrm_api3('OptionValue', 'get', ['option_group_id' => "contract_cancel_reason"])['values'] as $cancelReason){
      $cancelOptions[$cancelReason['value']] = $cancelReason['label'];
    };
    $this->addRule('activity_date', 'Scheduled date is required for a cancellation', 'required');
    $this->add('select', 'cancel_reason', ts('Cancellation reason'), array('' => '- none -') + $cancelOptions, true, array('class' => 'crm-select2'));

  }
  function addPauseFields(){

    // Resume date
    $this->addDate('resume_date', ts('Resume Date'), TRUE, array('formatType' => 'activityDate'));

  }

  function setDefaults($defaultValues = null, $filter = null){
    if(isset($this->membership[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')])){
      $defaults['recurring_contribution'] = $this->membership[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')];
    }

    $defaults['membership_type_id'] = $this->membership['membership_type_id'];

    if(isset($this->membership['campaign_id'])){
      $defaults['campaign_id'] = $this->membership['campaign_id'];
    }

    list($defaults['activity_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDate');


    parent::setDefaults($defaults);
  }

  function validate(){

    $submitted = $this->exportValues();

    $date = DateTime::createFromFormat('m/d/Y', $submitted['activity_date']);
    if($date < DateTime::createFromFormat('Y-m-d H:i:s', date_format(new DateTime(''), 'Y-m-d 00:00:00'))){
      HTML_QuickForm::setElementError ( 'activity_date', 'Activity date must be either today (i.e. make the change now) or in the future');
    }

    if($this->modificationActivity->getAction() == 'pause'){
      $activityDate = DateTime::createFromFormat('m/d/Y', $submitted['activity_date']);
      $resumeDate = DateTime::createFromFormat('m/d/Y', $submitted['resume_date']);
      if($activityDate > $resumeDate){
        HTML_QuickForm::setElementError ( 'resume_date', 'Resume date must be after the scheduled pause date');
      }
    }

    return parent::validate();
  }

  function postProcess(){

    // Construct a call to contract.modify
    // The following fields to be submitted in all cases
    $submitted = $this->exportValues();
    $params['id'] = $this->get('id');
    $params['action'] = $this->modificationActivity->getAction();
    $params['medium_id'] = $submitted['activity_medium'];
    $params['note'] = $submitted['activity_details'];

    //If the date was set, convert it to the necessary format
    if($submitted['activity_date']){
      $activityDate = DateTime::createFromFormat('m/d/Y', $submitted['activity_date']);
      $params['date'] = $activityDate->format('Y-m-d');
    }


    // If this is an update or a revival
    if(in_array($this->modificationActivity->getAction(), array('update', 'revive'))){
      $params['membership_payment.membership_recurring_contribution'] = $submitted['recurring_contribution'];
      $params['membership_type_id'] = $submitted['membership_type_id'];
      $params['campaign_id'] = $submitted['campaign_id'];

    // If this is a cancellation
    }elseif($this->modificationActivity->getAction() == 'cancel'){
      $params['membership_cancellation.membership_cancel_reason'] = $submitted['cancel_reason'];

    // If this is a pause
    }elseif($this->modificationActivity->getAction() == 'pause'){
      $resumeDate = DateTime::createFromFormat('m/d/Y', $submitted['resume_date']);
      $params['resume_date'] = $resumeDate->format('Y-m-d');
    }
    civicrm_api3('contract', 'modify', $params);
  }
}
