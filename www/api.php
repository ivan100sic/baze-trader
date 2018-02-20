<?php

require_once 'sql.php';

function __get__($id) {
	if (isset($_GET[$id])) {
		return $_GET[$id];
	}
	return "";
}

$magic_api_key = "flawle3s3s3s3ecurity";

$type = __get__("type");

if ($type == "new_user") {
	$user_email = __get__("user_email");
	$user_password = __get__("user_password");

	$result = [];

	$ok = SQL::run("insert into user(user_email, user_password) values (?, fullhash(?, ?))",
		[$user_email, $user_email, $user_password]);

	$api_key = SQL::get("select user_password from user where
		user_email = ?", [$user_email]);

	if ($ok) {
		$result['status'] = 'ok';
		$result['api-key'] = $apy_key;
	} else {
		$result['status'] = 'failed';
	}

	echo json_encode($result);
	exit();
}

if ($type == "get_api_key") {
	$user_email = __get__("user_email");
	$user_password = __get__("user_password");

	$result = [];

	$api_key = SQL::get("select user_password as pw from user where
		user_email = ?", [$user_email]);

	$expected_api_key = SQL::get("select fullhash(?, ?) as pw",
		[$user_email, $user_password]);

	if (count($api_key) == 0) {
		$result['status'] = 'no such account';
	} else if ($api_key[0]['pw'] != $expected_api_key[0]['pw']) {
		$result['status'] = 'authentication failed';
		$result['dbg1'] = $api_key[0]['pw'];
		$result['dbg2'] = $expected_api_key[0]['pw'];
	} else {
		$result['status'] = 'ok';
		$result['api_key'] = $api_key[0]['pw'];
	}

	echo json_encode($result);
	exit();
}

if ($type == "get_wallets") {
	// auth
	$api_key = __get__("api_key");

	$user_id = SQL::get("select user_id from user where
		user_password = ?", [$api_key]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];

		$wallets = SQL::get("select wallet_id, wallet_name, currency_code, wallet_amount
			from wallet where user_id = ?", [$user_id]);

		$result = ["status" => "ok", "wallets" => $wallets];
	}

	echo json_encode($result);
	exit();
}

if ($type == "get_currencies") {
	$currencies = SQL::get("select currency_code, currency_name from currency");

	$result = ["status" => "ok", "currencies" => $currencies];

	echo json_encode($result);
	exit();
}