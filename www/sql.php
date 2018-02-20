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

	/*
	static function dump() {
		// Dependencies:
		// tasks -> users (task author)
		// submissions -> tasks (for which task was the submission made)
		// submissions -> users (who made the submission)
		// testcases -> tasks (which task does the test case belong to)
		// test_runs -> submissions
		// test_runs -> testcases
		// users_permissions -> users, permissions

		// Note: users does not depend on any other table
		// permissions table is fixed and is not dumped

		$tables = [
			"users" => [
				"id", "username", "password", "email", "created_on"
			],
			"tasks" => [
				"id", "name", "statement", "author", "created_on", "status"
			],
			"submissions" => [
				"id", "user_id", "task_id", "source", "created_on", "status"
			],
			"testcases" => [
				"id", "name", "task_id", "source_input", "source_output", "instruction_limit"
			],
			"test_runs" => [
				"submission_id", "testcase_id", "status"
			],
			"users_permissions" => [
				"user_id", "permission_id"
			]
		];

		$str = "use skoj;\n\n";

		foreach ($tables as $name => $fields) {
			$columns = implode(", ", $fields);
			$db = SQL::get("select * from $name", []);
			$str .= "insert into $name($columns) values\n";
			$entries = [];
			foreach($db as $row) {
				$entry = [];
				foreach ($fields as $field) {
					$s = SQL::esc($row[$field]);
					$entry[] = "'$s'";
				}
				$entry = implode(", ", $entry);
				$entries[] = "\t($entry)\n";
			}
			$str .= implode(', ', $entries);
			$str .= "\t;\n";
		}

		return $str;
	}
	*/
}
	
?>