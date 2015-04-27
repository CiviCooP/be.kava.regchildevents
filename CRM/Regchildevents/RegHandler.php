<?php

/**
 * Class CRM_Regchildevents_RegHandler
 */
class CRM_Regchildevents_RegHandler {

	/**
	 * Called by civicrm_hook_post handler, only for object Participant.
	 * @param CRM_Event_BAO_Participant $participant Participant
	 * @param string $operation Operation
	 * @return bool Success
	 */
	public function handleRegistration($participant, $operation) {

		// Check if this is a create / edit action
		// Deleting appears not to be possible with a post hook -> object already deleted.
		if(!in_array($operation, array('create', 'edit', 'delete'))) {
			return false;
		}

        // If operation is delete, fetch participant from session (see pre hook in regchildevents.php)
        if($operation == 'delete') {
            $participant = $this->getStoredParticipant();
            if(!$participant) {
                return false;
            }
        }

		// Get event
		$event = civicrm_api3('Event', 'getsingle', array(
			'id' => $participant->event_id,
		));

		// Check what we need to process and show
		if(self::checkIfActiveForEvent($event)) {

			// This is a parent event and the 'Register for child events' option is enabled.

			// Process registrations (see next function)
			$childEventIds = $this->getChildEventIds($event);
			$count = $this->processRegistrations($operation, $participant, $childEventIds);

			// Show status message
			if($this->isBackendPage()) {
				$msg = ts('Updated registration for %1 child event(s).', array(1 => $count, 'domain' => 'be.kava.regchildevents'));
				$title = ts('Event Registration', array('domain' => 'be.kava.regchildevents'));
				CRM_Core_Session::setStatus($msg, $title, 'success');
			}

		}

		return true;
	}

	/**
	 * Handle participant registration for each (child) event
	 * @param string $operation Operation: create or edit
	 * @param CRM_Event_BAO_Participant $participant Participant
	 * @param array $childEventIds Child event IDs
	 * @return int Number of event updated
	 */
	public function processRegistrations($operation, $participant, $childEventIds) {

		$count = 0;
		foreach($childEventIds as $ceid) {

			$count ++;

			switch($operation) {
				case 'create':
					$this->addChildRegistration($ceid, $participant);
					break;
				case 'edit':
					$this->updateChildRegistration($ceid, $participant);
					break;
				case 'delete':
				    $this->deleteChildRegistration($ceid, $participant);
				    break;
			}
		}

		return $count;
	}

    /**
     * Stores participant in session for later use (for delete operations, see _pre hook)
     * @param $id int Participant ID
     * @return bool Success
     */
    public function storeParticipant($id) {

        try {
            $participant = civicrm_api3('Participant', 'getsingle', array(
                'id' => $id,
            ));
            $session = CRM_Core_Session::singleton();
            $session->set('regchildevents_participant', (object)$participant);
            return true;
        } catch(CiviCRM_API3_Exception $e) {
            return false; // Participant not found, ignoring silently
        }
    }

    /**
     * Gets participant from session (stored there for delete operations)
     * @return CRM_Event_BAO_Participant|null Participant
     */
    private function getStoredParticipant() {

        $session = CRM_Core_Session::singleton();
        $part = $session->get('regchildevents_participant');
        $session->set('regchildevents_participant', null);
        return $part;
    }

	/**
	 * Check whether this extension should handle registrations for this event
	 * @param mixed $event Event
	 * @return bool Extension is active
	 * @throws CRM_Regchildevents_Exception If custom field is not defined
	 */
	public static function checkIfActiveForEvent($event) {

		try {
			$customField = civicrm_api3('CustomField', 'getsingle', array(
				'name' => "register_for_child_events",
			));
			$customKey = 'custom_' . $customField['id'];
		} catch(CiviCRM_API3_Exception $e) {
			throw new CRM_Regchildevents_Exception('Custom field register_for_child_events is not defined.');
		}

		if(!$event || !array_key_exists($customKey, $event) || $event[$customKey] != 1)
			return false;

		return true;
	}

	/**
	 * @param array $event Event
	 * @return array|bool Event IDs or false
	 */
	private function getChildEventIds($event) {

		$reResult = civicrm_api3('RecurringEntity', 'get', array(
			'entity_table' => 'civicrm_event',
			'parent_id'    => $event['id'],
		));
		if(!$reResult || $reResult['count'] == 0) {
			return false;
		}

		$ret = array();
		foreach($reResult['values'] as $v) {
			if($v['entity_id'] == $event['id'])
				continue;

			$ret[] = $v['entity_id'];
		}

		return $ret;
	}

	/**
	 * @param array $event Event
	 * @return mixed|bool Parent event or false
	 */
	private function getParentEvent($event) {

		try {
			$reResult = civicrm_api3('RecurringEntity', 'getsingle', array(
				'entity_table' => 'civicrm_event',
				'entity_id'    => $event['id'],
			));
			$parentId = $reResult['parent_id'];

			if(!$parentId)
				return false;

			return civicrm_api3('Event', 'getsingle', array(
				'id' => $parentId,
			));

		} catch(CiviCRM_API3_Exception $e) {
			return false;
		}
	}

	/**
	 * Adds registration for a (child) event
	 * @param int $event_id Event ID
	 * @param CRM_Event_DAO_Participant $participant Participant
	 * @return bool Success
	 */
	private function addChildRegistration($event_id, $participant) {

		// Check if participant already exists
		$count = civicrm_api3('Participant', 'getcount', array(
			'contact_id' => $participant->contact_id,
			'event_id'   => $event_id,
		));
		if($count > 0) {
			return $this->updateChildRegistration($event_id, $participant);
		}

		// Add registration
		$res = civicrm_api3('Participant', 'create', array(
			'contact_id'    => $participant->contact_id,
			'event_id'      => $event_id,
			'status_id'     => $participant->status_id,
			'role_id'       => $participant->role_id,
			'register_date' => $participant->register_date,
			'source'        => 'Auto-registered for child event',
		));
		return $res->is_error;
	}

	/**
	 * Updates registration for a (child) event
	 * @param int $event_id Event ID
	 * @param CRM_Event_DAO_Participant $participant Participant
	 * @return bool Success
	 */
	private function updateChildRegistration($event_id, $participant) {

		$res = civicrm_api3('Participant', 'get', array(
			'contact_id' => $participant->contact_id,
			'event_id'   => $event_id,
		));
		if($res['count'] == 0) {
			return $this->addChildRegistration($event_id, $participant);
		}

		// We'll update the participant status for a child event unless it's "Attended" or "No-show" - we'll assume that's a status you'd want to set per child event.
		$statuses = CRM_Event_PseudoConstant::participantStatus();
		$status_attended = array_search('Attended', $statuses);
		$status_noshow = array_search('No-show', $statuses);

		// Walk all registrations and update them.
		foreach($res['values'] as $registration) {

			$params = array(
				'id'            => $registration['id'],
				'role_id'       => $participant->role_id,
				'register_date' => $participant->register_date,
			);
			if($participant->status_id != $status_attended && $participant->status_id != $status_noshow) {
				$params['status_id'] = $participant->status_id;
			}
			civicrm_api3('Participant', 'create', $params);
		}

		return true;
	}

	/**
	 * Deletes registration for a (child) event
	 * @param int $event_id Event ID
	 * @param CRM_Event_DAO_Participant $participant Participant
	 * @return bool Success
	 */
	private function deleteChildRegistration($event_id, $participant) {

		$res = civicrm_api3('Participant', 'get', array(
			'contact_id' => $participant->contact_id,
			'event_id' => $event_id,
		));
		if($res['count'] == 0) {
			return true;
		}

		foreach($res['values'] as $registration) {

			civicrm_api3('Participant', 'delete', array(
				'id' => $registration['id'],
			));
		}

		return true;
	}

	/**
	 * Check if this is a backend page or an event registration page.
	 * There's probably a much better way to do this?
	 * @return bool Is backend page
	 */
	private function isBackendPage() {
		return (!strpos($_SERVER['REQUEST_URI'], 'event/register'));
	}

}