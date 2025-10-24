<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerPopupScheduledReportSubscriptionEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'recipientid' =>			'id',
			'old_recipientid' =>		'id',
			'recipient_type' =>			'in '.ZBX_REPORT_RECIPIENT_TYPE_USER.','.ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP,
			'recipient_name' =>			'string',
			'recipient_inaccessible' =>	'in 0,1',
			'creatorid' =>				'id',
			'creator_type' =>			'in '.ZBX_REPORT_CREATOR_TYPE_USER.','.ZBX_REPORT_CREATOR_TYPE_RECIPIENT,
			'creator_name' =>			'string',
			'exclude' =>				'in '.ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE,
			'userids' =>				'array',
			'usrgrpids' =>				'array',
			'edit' =>					'in 1',
			'update' =>					'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateSubscription();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateSubscription(): bool {
		if (!$this->hasInput('update')) {
			return true;
		}

		$recipientid = $this->getInput('recipientid', 0);

		if (!$recipientid) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Recipient'), _('cannot be empty')));

			return false;
		}

		$recipient_type = $this->getInput('recipient_type', ZBX_REPORT_RECIPIENT_TYPE_USER);

		if (($recipient_type == ZBX_REPORT_RECIPIENT_TYPE_USER
					&& in_array($recipientid, $this->getInput('userids', [])))
				|| ($recipient_type == ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP
					&& in_array($recipientid, $this->getInput('usrgrpids', [])))) {
			if ($this->getInput('edit', 0) == 1 && $recipientid == $this->getInput('old_recipientid', 0)) {
				return true;
			}

			error(_('Recipient already exists.'));

			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction(): void {
		$data = [
			'action' => $this->getAction(),
			'edit' => 0,
			'recipientid' => 0,
			'old_recipientid' => 0,
			'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER,
			'recipient_name' => '',
			'recipient_inaccessible' => 0,
			'creatorid' => 0,
			'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER,
			'creator_name' => ''
		];
		$this->getInputs($data, array_keys($data));

		if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
			$data['exclude'] = $this->getInput('exclude', ZBX_REPORT_EXCLUDE_USER_FALSE);
			$data['userids'] = $this->getInput('userids', []);
		}
		else {
			$data['usrgrpids'] = $this->getInput('usrgrpids', []);
		}

		$data['recipient_ms'] = ($data['recipientid'] != 0)
			? [['id' => $data['recipientid'], 'name' => $data['recipient_name']]]
			: [];

		$data += [
			'title' => _('Subscription'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'js_validation_rules' => (new CFormValidator(
				CControllerPopupScheduledReportSubscriptionCheck::getValidationRules()
			))->getRules()
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
