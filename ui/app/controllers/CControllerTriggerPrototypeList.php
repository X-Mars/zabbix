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


class CControllerTriggerPrototypeList extends CController {

	private array $parent_discovery = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>				'required|in '.implode(',', ['host', 'template']),
			'page' =>					'ge 1',
			'parent_discoveryid' =>		'required|db items.itemid',
			'sort' =>					'in '.implode(',', ['description', 'priority', 'status', 'discover']),
			'sortorder' =>				'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'uncheck' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$options = [
			'output' => ['itemid', 'name', 'hostid', 'flags'],
			'selectDiscoveryData' => ['parent_itemid'],
			'selectHosts' => ['status'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		];

		$parent_discovery = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

		if (!$parent_discovery) {
			return false;
		}

		$this->parent_discovery = reset($parent_discovery);

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction() {
		$data = [
			'parent_discoveryid' => $this->getInput('parent_discoveryid'),
			'discovery_rule' => $this->parent_discovery,
			'hostid' => $this->parent_discovery['hostid'],
			'triggers' => [],
			'dependency_triggers' => [],
			'context' => $this->getInput('context'),
			'uncheck' => $this->hasInput('uncheck'),
			'is_parent_discovered' => $this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED
		];

		$prefix = ($data['context'] === 'host') ? 'web.hosts.' : 'web.templates.';

		$sort_field = $this->getInput('sort', CProfile::get($prefix.'trigger.prototype.list.sort', 'description'));
		$sort_order = $this->getInput('sortorder',
			CProfile::get($prefix.'trigger.prototype.list.sortorder', ZBX_SORT_UP)
		);

		CProfile::update($prefix.'trigger.prototype.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update($prefix.'trigger.prototype.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data += [
			'sort' => $sort_field,
			'sortorder' => $sort_order
		];

		$is_template_lld = $this->parent_discovery['hosts'][0]['status'] == HOST_STATUS_TEMPLATE;

		if (($data['context'] === 'template' && $is_template_lld)
				|| ($data['context'] === 'host' && !$is_template_lld)) {
			$options = [
				'output' => ['triggerid', $sort_field],
				'discoveryids' => $data['parent_discoveryid'],
				'sortfield' => $sort_field,
				'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
				'editable' => true
			];

			$data['triggers'] = API::TriggerPrototype()->get($options);

			if ($this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
				$data['source_link_data'] = [
					'parent_itemid' => $this->parent_discovery['discoveryData']['parent_itemid'],
					'name' => $this->parent_discovery['name']
				];
			}
		}

		order_result($data['triggers'], $sort_field, $sort_order);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('trigger.prototype.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['triggers'], $sort_order, (new CUrl('zabbix.php'))
			->setArgument('action', 'trigger.prototype.list')
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('context', $data['context'])
		);

		$data['triggers'] = API::TriggerPrototype()->get([
			'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'templateid', 'recovery_mode',
				'recovery_expression', 'opdata', 'discover', 'flags'
			],
			'selectHosts' => ['hostid', 'host'],
			'selectDependencies' => ['triggerid', 'description'],
			'selectTags' => ['tag', 'value'],
			'selectDiscoveryRule' => ['itemid'],
			'selectDiscoveryRulePrototype' => ['itemid'],
			'selectDiscoveryData' => ['parent_triggerid'],
			'triggerids' => array_column($data['triggers'], 'triggerid')
		]);

		order_result($data['triggers'], $sort_field, $sort_order);

		$data['tags'] = makeTags($data['triggers'], true, 'triggerid');

		$dep_trigger_ids = [];
		foreach ($data['triggers'] as $trigger) {
			foreach ($trigger['dependencies'] as $dep_trigger) {
				$dep_trigger_ids[$dep_trigger['triggerid']] = true;
			}
		}

		if ($dep_trigger_ids) {
			$dep_trigger_ids = array_keys($dep_trigger_ids);

			$dependency_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'status', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $dep_trigger_ids,
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
				'preservekeys' => true
			]);

			$dependency_trigger_prototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'status', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $dep_trigger_ids,
				'preservekeys' => true
			]);

			$data['dependencyTriggers'] = $dependency_triggers + $dependency_trigger_prototypes;

			foreach ($data['triggers'] as &$trigger) {
				order_result($trigger['dependencies'], 'description', ZBX_SORT_UP);
			}
			unset($trigger);

			foreach ($data['dependencyTriggers'] as &$dependencyTrigger) {
				order_result($dependencyTrigger['hosts'], 'name', ZBX_SORT_UP);
			}
			unset($dependencyTrigger);
		}

		$data['parent_templates'] = getTriggerParentTemplates($data['triggers'], ZBX_FLAG_DISCOVERY_PROTOTYPE);
		$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of trigger prototypes'));
		$this->setResponse($response);
	}
}
