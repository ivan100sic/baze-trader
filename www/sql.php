<?php

class SQL {

	static private $conn_var = NULL;
	
	// Maintain a connection for the duration of page render
	static private function conn() {
		if (SQL::$conn_var === NULL) {
			// For local testing/development
			error_reporting(0);
			SQL::$conn_var = new mysqli('localhost', 'trader', '0security', 'is43bt');
			error_reporting(E_ALL & ~ E_NOTICE & ~ E_STRICT & ~ E_DEPRECATED);
			if (SQL::$conn_var->connect_error) {
				throw new Exception("SQL");
			}
		}
		SQL::$conn_var->set_charset("utf8");
		return SQL::$conn_var;
	}
	
	static private function do_it($query, $params, $is_select) {
		$conn = SQL::conn();
		$st = $conn->prepare($query);
		if ($st) {
			$types = '';
			for ($i = 0; $i < count($params); $i++) {
				$types .= 's';
				$params[$i] = (string)$params[$i];
			}
			// sve je string
			if (count($params) > 0) {
				$st->bind_param($types, ...$params);
			}
			if (!$is_select) {
				$retval = $st->execute();
				$st->close();
				return $retval;
			} else {
				$st->execute();
			}
			$result = $st->get_result();
			$st->close();
			$all_results = [];
			while ($row = $result->fetch_assoc()) {
				$all_results[] = $row;
			}
			return $all_results;
		}
		throw new Exception("SQL");	
	}
	
	static function get($query, $params) {
		return SQL::do_it($query, $params, true);
	}
	
	static function run($query, $params) {
		return SQL::do_it($query, $params, false);
	}

	static function last_insert_id() {
		return SQL::get("select last_insert_id() as id", [])[0]['id'];
	}

	// 
	static function esc($str) {
		return SQL::conn()->real_escape_string($str);
	}
}
