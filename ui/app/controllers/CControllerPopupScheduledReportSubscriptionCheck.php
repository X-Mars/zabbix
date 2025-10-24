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

class CControllerPopupScheduledReportSubscriptionCheck extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'old_recipientid' => ['id'],
			'recipient_type' => ['integer', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER, ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]],
			'recipientid' => ['id', 'required'],
			'recipient_name' => ['string'],
			'recipient_inaccessible' => ['boolean'],
			'creator_type' => ['integer', 'required', 'in' => [ZBX_REPORT_CREATOR_TYPE_USER, ZBX_REPORT_CREATOR_TYPE_RECIPIENT]],
			'exclude' => ['integer', 'required',
				'in' => [ZBX_REPORT_EXCLUDE_USER_FALSE, ZBX_REPORT_EXCLUDE_USER_TRUE],
				'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]
			],
			'userids' => ['array', 'field' => ['db users.userid'], 'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]],
			'usrgrpids' => ['array', 'field' => ['db usrgrp.usrgrpid'], 'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]]],
			'edit' => ['boolean']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules()) && $this->validateSubscription();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add subscription'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function validateSubscription(): bool {
		$recipient_type = $this->getInput('recipient_type', ZBX_REPORT_RECIPIENT_TYPE_USER);
		$recipientid = $this->getInput('recipientid');

		if (($recipient_type == ZBX_REPORT_RECIPIENT_TYPE_USER
					&& in_array($recipientid, $this->getInput('userids', [])))
				|| ($recipient_type == ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP
					&& in_array($recipientid, $this->getInput('usrgrpids', [])))) {
			if ($this->getInput('edit', 0) == 1 && $recipientid == $this->getInput('old_recipientid', 0)) {
				return true;
			}

			$this->addFormError('/recipientid', _('Recipient already exists.'), CFormValidator::ERROR_LEVEL_PRIMARY);

			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction(): void {
		$data = [];
		$this->getInputs($data, ['recipientid', 'old_recipientid', 'recipient_type', 'recipient_name',
			'recipient_inaccessible', 'creator_type', 'edit'
		]);

		if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
			$data['exclude'] = $this->getInput('exclude');
		}

		if ($data['creator_type'] == ZBX_REPORT_CREATOR_TYPE_USER) {
			$data['creatorid'] = CWebUser::$data['userid'];
			$data['creator_name'] = getUserFullname(CWebUser::$data);
			$data['creator_inaccessible'] = 0;
		}
		else {
			$data['creatorid'] = 0;
			$data['creator_name'] = _('Recipient');
			$data['creator_inaccessible'] = $data['recipient_inaccessible'];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
