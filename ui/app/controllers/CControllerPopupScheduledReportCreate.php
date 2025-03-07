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


class CControllerPopupScheduledReportCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$ret = $this->validateInput(CControllerScheduledReportCreate::getValidationRules())
			&& $this->validateWeekdays() && $this->validateTimePeriods();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot create scheduled report'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	/**
	 * Validate days of the week.
	 *
	 * @return bool
	 */
	private function validateWeekdays(): bool {
		$cycle = $this->getInput('cycle', ZBX_REPORT_CYCLE_DAILY);
		$weekdays = array_sum($this->getInput('weekdays', []));

		if ($cycle == ZBX_REPORT_CYCLE_WEEKLY && $weekdays == 0) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Repeat on'),
				_('at least one day of the week must be selected'))
			);

			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction() {
		$report = [];

		$this->getInputs($report, ['userid', 'name', 'dashboardid', 'period', 'cycle', 'subject', 'message',
			'description', 'status'
		]);

		if ($report['cycle'] == ZBX_REPORT_CYCLE_WEEKLY) {
			$report['weekdays'] = array_sum($this->getInput('weekdays', []));
		}

		$report['start_time'] = ($this->getInput('hours') * SEC_PER_HOUR) + ($this->getInput('minutes') * SEC_PER_MIN);

		if ($this->getInput('active_since') !== '') {
			$report['active_since'] = $this->getInput('active_since');
		}
		if ($this->getInput('active_till') !== '') {
			$report['active_till'] = $this->getInput('active_till');
		}

		$report['users'] = [];
		$report['user_groups'] = [];

		foreach ($this->getInput('subscriptions', []) as $subscription) {
			if ($subscription['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
				$report['users'][] = [
					'userid' => $subscription['recipientid'],
					'exclude' => $subscription['exclude'],
					'access_userid' => $subscription['creatorid']
				];
			}
			else {
				$report['user_groups'][] = [
					'usrgrpid' => $subscription['recipientid'],
					'access_userid' => $subscription['creatorid']
				];
			}
		}

		$result = API::Report()->create($report);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Scheduled report created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create scheduled report'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}

	protected function validateTimePeriods(): bool {
		$active_since = $this->getInput('active_since', '');
		$active_till = $this->getInput('active_till', '');

		if ($active_since === '' || $active_till === '') {
			return true;
		}

		$absolute_time_parser = new CAbsoluteTimeParser();

		$absolute_time_parser->parse($active_since);
		$active_since_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		$absolute_time_parser->parse($active_till);
		$active_till_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		if ($active_since_ts >= $active_till_ts) {
			$message = _s('"%1$s" must be an empty string or greater than "%2$s".', _('End date'), _('Start date'));
			$this->addFormError('/active_till', $message, CFormValidator::ERROR_LEVEL_PRIMARY);

			return false;
		}

		return true;
	}
}
