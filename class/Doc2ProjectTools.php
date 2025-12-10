<?php
/* Copyright (C) 2025 ATM Consulting
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

class Doc2ProjectTools
{
	/**
	 * SQL Function to add product id extra field on Tasks
	 *
	 * @return bool
	 */
	public static function addProductIdOnTasks(): bool
	{
		global $db;

		$db->begin();

		$sql = "UPDATE {$db->prefix()}projet_task_extrafields AS ptex";
		$sql .= " JOIN {$db->prefix()}projet_task AS pt ON pt.rowid = ptex.fk_object";
		$sql .= " SET ptex.fk_product = (";
		$sql .= " CASE";
		// TA% = Propal line id
		$sql .= " WHEN pt.ref LIKE 'TA%' AND SUBSTRING(pt.ref, 3) REGEXP '^[0-9]+$' THEN (";
		$sql .= " SELECT pdet.fk_product";
		$sql .= " FROM {$db->prefix()}propaldet AS pdet";
		$sql .= " WHERE pdet.rowid = CAST(SUBSTRING(pt.ref, 3) AS UNSIGNED)";
		$sql .= " LIMIT 1)";
		// T% = Order line Id
		$sql .= " WHEN pt.ref LIKE 'T%' AND NOT pt.ref LIKE 'TA%' AND NOT pt.ref LIKE 'TK%'";
		$sql .= " AND SUBSTRING(pt.ref, 2) REGEXP '^[0-9]+$' THEN (";
		$sql .= " SELECT cd.fk_product";
		$sql .= " FROM {$db->prefix()}commandedet AS cd";
		$sql .= " WHERE cd.rowid = CAST(SUBSTRING(pt.ref, 2) AS UNSIGNED)";
		$sql .= " LIMIT 1)";
		$sql .= " ELSE NULL END)";
		$sql .= " WHERE (pt.ref LIKE 'TA%' OR (pt.ref LIKE 'T%' AND NOT pt.ref LIKE 'TK%'));";

		$resql = $db->query($sql);
		if (!$resql) {
			$db->rollback();
			dol_syslog(__METHOD__ . '::query SQL Error : ' . $db->lasterror());
			return false;
		}

		$db->commit();
		return true;
	}
}
