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


class CScatterPlot {

	static SCATTER_PLOT_MARKER_MIN_SIZE = 6;

	/**
	 * @type {SVGElement}
	 */
	#svg;

	/**
	 * @type {CWidgetScatterPlot}
	 */
	#widget;

	/**
	 * @type {number}
	 */
	#dimX;

	/**
	 * @type {number}
	 */
	#dimY;

	/**
	 * @type {number}
	 */
	#dimW;

	/**
	 * @type {number}
	 */
	#dimH;

	/**
	 * @type {Object}
	 */
	#metrics;

	/**
	 * @type {Object}
	 */
	#paths;

	#moveTimeOut = null;

	constructor(svg, widget, options) {
		this.#svg = svg;

		this.#widget = widget;

		this.#dimX = options.dims.x;
		this.#dimY = options.dims.y;
		this.#dimW = options.dims.w;
		this.#dimH = options.dims.h;
		this.#metrics = options.hintbox_data.metrics;
		this.#paths = options.hintbox_data.paths;

		this.#svg.setAttribute('unselectable', 'true');
		this.#svg.style.userSelect = 'none';
	}

	activate() {
		this.#svg.dataset.hintbox = '1';
		this.#svg.dataset.hintboxStatic = '1';
		this.#svg.dataset.hintboxDelay = '200';
		this.#svg.dataset.hintboxStaticReopenOnClick = '1';

		this.#svg.addEventListener('mousemove', this.#mouseMoveHandler);
		this.#svg.addEventListener('mouseleave', this.#mouseLeaveHandler);
		this.#svg.addEventListener('onShowStaticHint', this.#onStaticHintboxOpen);
	}

	deactivate() {
		delete this.#svg.dataset.hintbox;
		delete this.#svg.dataset.hintboxStatic;
		delete this.#svg.dataset.hintboxDelay;
		delete this.#svg.dataset.hintboxStaticReopenOnClick;

		this.#svg.removeEventListener('mousemove', this.#mouseMoveHandler);
		this.#svg.removeEventListener('mouseleave', this.#mouseLeaveHandler);
		this.#svg.removeEventListener('onShowStaticHint', this.#onStaticHintboxOpen);
	}

	#mouseMoveHandler = (e) => {
		const svg_rect = this.#svg.getBoundingClientRect();

		const offsetX = e.clientX - svg_rect.left;
		const offsetY = e.clientY - svg_rect.top;

		// Check if mouse in the horizontal area in which hintbox must be shown.
		const in_x = this.#dimX <= offsetX && offsetX <= this.#dimX + this.#dimW;
		const in_values_area = in_x && this.#dimY <= e.offsetY && e.offsetY <= this.#dimY + this.#dimH;

		clearTimeout(this.#moveTimeOut);

		if (in_values_area) {
			this.#setHelperPosition(e);
			this.#removePointHighlight();

			this.#moveTimeOut = setTimeout(() => {
				const included_paths = this.#findPoints(offsetX, offsetY);

				if (included_paths.length > 0) {
					this.#highlightPoints(included_paths);

					included_paths.sort((p1, p2) => {
						if (p1.x !== p2.x) {
							return p2.x - p1.x;
						}

						return p1.y - p2.y;
					});

					this.#showHintbox(included_paths)
				}
			}, 100);
		}
		else {
			this.#mouseLeaveHandler();
		}
	}

	#mouseLeaveHandler = () => {
		clearTimeout(this.#moveTimeOut);

		this.#removeHintboxContents();
		this.#hideHelper();
	}

	#onStaticHintboxOpen = () => {
		const hintbox = this.#svg.hintBoxItem[0];
		const hintbox_items = hintbox.querySelectorAll('.has-broadcast-data');

		for (const item of hintbox_items) {
			const {itemid, ds} = item.dataset;
			const itemids = [itemid];

			item.addEventListener('click', () => {
				this.#widget.updateItemBroadcast(itemids, ds);
				this.#markSelectedHintboxItems(hintbox);
			});
		}

		this.#markSelectedHintboxItems(hintbox);
	}

	#markSelectedHintboxItems(hintbox) {
		const {itemid, ds} = this.#widget.getItemBroadcast();

		for (const item of hintbox.querySelectorAll('.has-broadcast-data')) {
			item.classList.toggle('selected', item.dataset.itemid == itemid && item.dataset.ds == ds);
		}
	}

	#setHelperPosition(e) {
		const svg_rect = this.#svg.getBoundingClientRect();

		const vertical_helper = this.#svg.querySelector('.scatter-plot-vertical-helper');

		vertical_helper.setAttribute('x1', e.clientX - svg_rect.left);
		vertical_helper.setAttribute('y1', this.#dimY);
		vertical_helper.setAttribute('x2', e.clientX - svg_rect.left);
		vertical_helper.setAttribute('y2', this.#dimY + this.#dimH);

		const horizontal_helper = this.#svg.querySelector('.scatter-plot-horizontal-helper');

		horizontal_helper.setAttribute('x1', this.#dimX);
		horizontal_helper.setAttribute('y1', e.clientY - svg_rect.top);
		horizontal_helper.setAttribute('x2', this.#dimX + this.#dimW);
		horizontal_helper.setAttribute('y2', e.clientY - svg_rect.top);
	}

	#hideHelper() {
		for (const helper of this.#svg.querySelectorAll('.svg-helper')) {
			helper.setAttribute('x1', -10);
			helper.setAttribute('x2', -10);
			helper.setAttribute('y1', -10);
			helper.setAttribute('y2', -10);
		}

		this.#removePointHighlight();
	}

	#showHintbox(included_paths) {
		if (included_paths) {
			this.#setHintboxContents(this.#getHintboxHtml(included_paths));
		}
		else {
			this.#removeHintboxContents();
		}
	}

	#setHintboxContents(html) {
		this.#svg.dataset.hintboxContents = html.outerHTML;
	}

	#removeHintboxContents() {
		delete this.#svg.dataset.hintboxContents;
	}

	// Find scatter plot metric paths that touches the given x and y.
	#findPoints(offset_x, offset_y) {
		const paths = [];

		const min_x = Math.round(offset_x) - CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;
		const max_x = Math.round(offset_x) + CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;

		const min_y = Math.round(offset_y) - CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;
		const max_y = Math.round(offset_y) + CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;

		for (let x = min_x; x < max_x; x++) {
			if (this.#paths[x]) {
				for (let y = min_y; y < max_y; y++) {
					if (this.#paths[x][y]) {
						paths.push({
							x,
							y,
							points: this.#paths[x][y]
						});
					}
				}
			}
		}

		return paths;
	}

	#highlightPoints(included_paths) {
		included_paths.forEach(path => {
			const x = path.x;
			const y = path.y;

			for (const point_to_highlight of this.#svg.querySelectorAll(`.point-${x}-${y}`)) {
				const href = point_to_highlight.dataset.id;

				point_to_highlight.setAttribute('href', '#highlight_' + href);
				point_to_highlight.classList.add('visible');
			}
		});
	}

	#removePointHighlight() {
		for (const highlighter_point of this.#svg.querySelectorAll(`.metric-point.visible`)) {
			const href = highlighter_point.dataset.id;

			highlighter_point.setAttribute('href', '#' + href);
			highlighter_point.classList.remove('visible');
		}
	}

	#getHintboxHtml(included_paths) {
		let rows_added = 0;

		const hintbox_container = document.createElement('div');
		hintbox_container.classList.add('svg-graph-hintbox');

		const html = document.createElement('ul');

		for (const paths of included_paths) {
			for (const point of paths.points) {
				const metric = this.#metrics[point.metric];
				const aggregation_name = metric.aggregation_name;
				const ds = metric.data_set;

				for (const time_interval of point.time_intervals) {
					const time_from = new CDate(time_interval.from * 1000);
					const time_to = new CDate(time_interval.to * 1000);

					for (const key of ['x_items', 'y_items']) {
						const items_data = Object.entries(metric[key]);

						const li = document.createElement('li');
						li.style.marginTop = key === 'x_items' && rows_added > 0 ? '10px' : null;
						li.append(`${aggregation_name}(`);

						let count = 0;
						for (const [itemid, name] of items_data) {
							count++;

							const item_span = document.createElement('span');
							item_span.classList.add('has-broadcast-data');
							item_span.dataset.itemid = itemid;
							item_span.dataset.ds = ds;
							item_span.innerText = name.toString();

							li.append(item_span);

							if (count !== items_data.length && count > 0) {
								li.append(', ');
							}
						}

						const color_span = document.createElement('span');
						color_span.style.color = point.color;
						color_span.classList.add('svg-graph-hintbox-icon-color', metric.marker_class);

						li.append(`): ${key === 'x_items' ? point.vx : point.vy}`, color_span);

						html.append(li);

						rows_added++;
					}

					const row = document.createElement('div');
					row.append(
						`${time_from.format(PHP_ZBX_FULL_DATE_TIME)} - ${time_to.format(PHP_ZBX_FULL_DATE_TIME)}`
					);

					html.append(row);
				}
			}
		}

		hintbox_container.append(html);

		return hintbox_container;
	}
}
