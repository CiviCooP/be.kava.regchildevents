<?php

require_once 'regchildevents.civix.php';

/**
 Default Civix hooks:
 */

function regchildevents_civicrm_config(&$config) {
  _regchildevents_civix_civicrm_config($config);
}

function regchildevents_civicrm_xmlMenu(&$files) {
  _regchildevents_civix_civicrm_xmlMenu($files);
}

function regchildevents_civicrm_install() {
  _regchildevents_civix_civicrm_install();
}

function regchildevents_civicrm_uninstall() {
  _regchildevents_civix_civicrm_uninstall();
}

function regchildevents_civicrm_enable() {
  _regchildevents_civix_civicrm_enable();
}

function regchildevents_civicrm_disable() {
  _regchildevents_civix_civicrm_disable();
}

function regchildevents_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _regchildevents_civix_civicrm_upgrade($op, $queue);
}

function regchildevents_civicrm_managed(&$entities) {
  _regchildevents_civix_civicrm_managed($entities);
}

function regchildevents_civicrm_caseTypes(&$caseTypes) {
  _regchildevents_civix_civicrm_caseTypes($caseTypes);
}

function regchildevents_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _regchildevents_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_pre().
 * Stores data for a delete operation so we can remove child event participants in the next step.
 * @param string $op Operation
 * @param string $objectName Object Name
 * @param int $id Object ID
 * @param mixed $params Parameters
 */
function regchildevents_civicrm_pre($op, $objectName, $id, &$params) {

    if($objectName == 'Participant' && $op == 'delete') {

        $handler = new CRM_Regchildevents_RegHandler();
        $handler->storeParticipant($id);
    }
}

/**
 * Implements hook_civicrm_post().
 * 1. Adds registrations for child events for participants if the option for the event is set.
 * 2. Modifies the child events slightly on creation of a recurring entity.
 * @param string $op Operation
 * @param string $objectName Object Name
 * @param int $objectId Object ID
 * @param mixed $objectRef Object Reference
 */
function regchildevents_civicrm_post($op, $objectName, $objectId, &$objectRef) {

  if($objectName == 'Participant' && $objectRef) {
    $handler = new CRM_Regchildevents_RegHandler();
    $handler->handleRegistration($objectRef, $op);
  }

  if($objectName == 'RecurringEntity' && $objectRef) {
    $handler = new CRM_Regchildevents_EventHandler();
    $handler->updateEvents($objectRef, $op);
  }

}
