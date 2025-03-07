<?php
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


/**
 * @var CView $this
 */

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('scheduledreport')))->removeId())
	->setId('scheduledreport-form')
	->setName('scheduledreport-form');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form->addItem(new CPartial('scheduledreport.formgrid.html', [
	'source' => 'popup',
	'form' => $form->getName()
] + $data));

$form->addItem((new CScriptTag('popup_scheduledreport_edit.init('.json_encode([
	'rules' => $data['js_validation_rules']
]).');'))->setOnDocumentReady());

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::REPORTS_SCHEDULEDREPORT_EDIT),
	'body' => $form->toString(),
	'script_inline' => $this->readJsFile('popup.scheduledreport.edit.js.php'),
	'buttons' => [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return popup_scheduledreport_edit.submit();'
		]
	]
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
