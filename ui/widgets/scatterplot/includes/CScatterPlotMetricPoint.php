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


namespace Widgets\ScatterPlot\Includes;

use CSvgCircle,
	CSvgCross,
	CSvgDiamond,
	CSvgGraph,
	CSvgRect,
	CSvgStar,
	CSvgTriangle,
	CTag,
	InvalidArgumentException;

class CScatterPlotMetricPoint extends CTag {

	public const MARKER_TYPE_ELLIPSIS = 0;
	public const MARKER_TYPE_SQUARE = 1;
	public const MARKER_TYPE_TRIANGLE = 2;
	public const MARKER_TYPE_DIAMOND = 3;
	public const MARKER_TYPE_STAR = 4;
	public const MARKER_TYPE_CROSS = 5;

	public const MARKER_ICONS = [
		self::MARKER_TYPE_ELLIPSIS => ZBX_ICON_ELLIPSE,
		self::MARKER_TYPE_SQUARE => ZBX_ICON_SQUARE,
		self::MARKER_TYPE_TRIANGLE => ZBX_ICON_TRIANGLE,
		self::MARKER_TYPE_DIAMOND => ZBX_ICON_DIAMOND,
		self::MARKER_TYPE_STAR => ZBX_ICON_STAR_FILLED,
		self::MARKER_TYPE_CROSS => ZBX_ICON_CROSS
	];

	private ?array $point;

	protected array $options;

	public function __construct(array $point, array $metric) {
		parent::__construct('use', true);

		$this->point = $point;

		$this->options = $metric['options'] + [
			'color' => CSvgGraph::SVG_GRAPH_DEFAULT_COLOR,
			'order' => $metric['order'],
			'key' => $metric['key'],
			'data_set' => $metric['data_set']
		];
	}

	public static function createMarker(int $marker_type, int $size, int $cx = 0, int $cy = 0): array {
		switch ($marker_type) {
			case self::MARKER_TYPE_ELLIPSIS:
				return [
					new CSvgCircle($cx, $cy, $size + 4),
					new CSvgCircle($cx, $cy, $size)
				];

			case self::MARKER_TYPE_SQUARE:
				$empty_coordinates = $cx - ($size + 4) / 2;
				$zero_coordinates = $cy - ($size / 2);

				return [
					new CSvgRect($empty_coordinates, $empty_coordinates, $size + 4, $size + 4),
					new CSvgRect($zero_coordinates, $zero_coordinates, $size, $size)
				];

			case self::MARKER_TYPE_TRIANGLE:
				return [
					new CSvgTriangle($cx, $cy, $size + 4, $size + 4),
					new CSvgTriangle($cx, $cy, $size, $size)
				];

			case self::MARKER_TYPE_DIAMOND:
				return [
					new CSvgDiamond($cx, $cy, $size + 4),
					new CSvgDiamond($cx, $cy, $size)
				];

			case self::MARKER_TYPE_STAR:
				return [
					new CSvgStar($cx, $cy, $size + 4),
					new CSvgStar($cx, $cy, $size)
				];

			case self::MARKER_TYPE_CROSS:
				return [
					new CSvgCross($cx, $cy, $size + 4),
					new CSvgCross($cx, $cy, $size)
				];
		}

		throw new InvalidArgumentException();
	}

	public function toString($destroy = true): string {
		$color = $this->point ? $this->point[4] : $this->options['color'];

		$this
			->addClass('metric-point')
			->addClass('point-'.round($this->point[0]).'-'.round($this->point[1]))
			->setAttribute('href', '#point_'.$this->options['marker'].'_'.$this->options['marker_size'])
			->setAttribute('x', $this->point[0])
			->setAttribute('y', $this->point[1])
			->setAttribute('fill-opacity', 1)
			->setAttribute('fill', $color)
			->setAttribute('stroke', $color)
			->setAttribute('data-id', 'point_'.$this->options['marker'].'_'.$this->options['marker_size']);

		return parent::toString($destroy);
	}
}
