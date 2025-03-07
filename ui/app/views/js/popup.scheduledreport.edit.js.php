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
?>

window.popup_scheduledreport_edit = new class {
	init({rules}) {
		this.overlay = overlays_stack.getById('scheduledreport-edit');
		this.form_element = document.getElementById('scheduledreport-form');
		this.form = new CForm(this.form_element, rules);
	}

	submit() {
		const fields = this.form.getAllValues();

		this.overlay.setLoading();
		this.form
			.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				this.#post(fields);
			});
	}

	#post(data) {
		const url = new Curl(this.form_element.getAttribute('action'));
		url.setArgument('action', 'popup.scheduledreport.create');

		fetch(url.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();
					return;
				}
				else if ('error' in response) {
					throw {error: response.error};
				}

				postMessageOk(response.success.title);

				if ('messages' in response.success) {
					postMessageDetails('success', response.success.messages);
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				location.href = location.href;
			})
			.catch((exception) => {
				this.overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title);

				message_box.insertBefore(this.form_element);
			})
			.finally(() => this.overlay.unsetLoading());
	}
}
