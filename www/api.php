<?php

require_once 'sql.php';

function __get__($id) {
	if (isset($_GET[$id])) {
		return $_GET[$id];
	}
	return "";
}

$magic_key = "flawle3s3s3s3ecurity";

$type = __get__("type");

/*
	new_user Kreira novog korisnika i vraca njegov API kljuc

	user_email Email novog korisnika
	user_password Sifra novog korisnika, koristi se pri dobavljanju API kljuca

	{status, ?api_key}
*/
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
		$result['api_key'] = $api_key[0]['user_password'];
	} else {
		$result['status'] = 'failed';
	}

	echo json_encode($result);
	exit();
}

/*
	get_api_key Vraca API kljuc za datog korisnika

	user_email Email korisnika
	user_password Sifra korisnika

	{status, ?api_key}
*/
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
	} else {
		$result['status'] = 'ok';
		$result['api_key'] = $api_key[0]['pw'];
	}

	echo json_encode($result);
	exit();
}

/*
	get_wallets Vraca sve novcanike za datog korisnika, mora autentikacija

	api_key API kljuc korisnika

	{status, ?[{wallet_id, wallet_name, currency_code, wallet_amount}]}
*/
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

/*
	get_currencies Vraca sve dostupne valute

	{status, [{currency_code, currency_name}]}
*/
if ($type == "get_currencies") {
	$currencies = SQL::get("select currency_code, currency_name from currency");

	$result = ["status" => "ok", "currencies" => $currencies];

	echo json_encode($result);
	exit();
}

/*
	TODO:

	new_wallet
	update_password
	get_trades_market
	get_trades_user
	get_trades_user_market
	new_trade
	cancel_trade
	get_transactions_user

	SUPER:

	credit_wallet
	get_users
	get_trades
	get_transactions
*/
