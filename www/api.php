<?php

require_once 'sql.php';

function __get__($id) {
	if (isset($_GET[$id])) {
		return $_GET[$id];
	}
	return "";
}

function json_encode_utf8($x) {
	return json_encode ($x, JSON_UNESCAPED_UNICODE);
}

$super_key = "flawle3s3s3s3ecurity";

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

	echo json_encode_utf8($result);
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

	echo json_encode_utf8($result);
	exit();
}

/*
	get_wallets Vraca sve novcanike za datog korisnika, mora autentikacija

	api_key API kljuc korisnika

	{status, ?wallets: [{wallet_id, wallet_name, currency_code, wallet_amount}]}
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

	echo json_encode_utf8($result);
	exit();
}

/*
	get_currencies Vraca sve dostupne valute

	{status, currencies: [{currency_code, currency_name}]}
*/
if ($type == "get_currencies") {
	$currencies = SQL::get("select currency_code, currency_name from currency");

	$result = ["status" => "ok", "currencies" => $currencies];

	echo json_encode_utf8($result);
	exit();
}

/*
	new_wallet Kreira novi wallet za korisnika

	api_key API kljuc korisnika
	wallet_name Ime novog walleta
	currency_code Valuta walleta

	{status, ?wallet_id}
*/
if ($type == "new_wallet") {
	// auth
	$api_key = __get__("api_key");

	$user_id = SQL::get("select user_id from user where
		user_password = ?", [$api_key]);

	$currency_code = SQL::get("select currency_code from currency
		where currency_code = ?", [__get__("currency_code")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else if (count($currency_code) == 0) {
		$result['status'] = 'bad currency_code';
	} else {
		$user_id = $user_id[0]['user_id'];

		$ok = SQL::run("insert into wallet(wallet_name, currency_code, wallet_amount,
			user_id) values (?,?,0,?)", [

			__get__("wallet_name"),
			__get__("currency_code"),
			$user_id

		]);

		if ($ok) {
			$result = ["status" => "ok", "wallet_id" => SQL::last_insert_id()];
		} else {
			$result = ["status" => "failed"];
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	update_password Menja sifru korisnika, ovo automatski menja i API kljuc

	api_key API kljuc korisnika
	new_user_password Nova sifra korisnika

	{status, ?api_key}
*/
if ($type == "update_password") {
	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];

		$ok = SQL::run("update user set user_password = fullhash(user_email, ?)
			where user_id = ?", [__get__('new_user_password'), $user_id]);

		if ($ok) {
			$result = [
				'status' => 'ok',
				'api_key' => SQL::get(
					"select user_password from user where user_id = ?",
					[$user_id]
				)
			];
		} else {
			$result['status'] = 'failed';
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	get_trades_market Daje sve aktivne (>0) ponude na nekom marketu

	currency_code_1 Valuta koja se kupuje
	currency_code_2 Valuta kojom se placa

	{status, ?buy: [{quantity, price}], ?sell: [{quantity, price}]}
*/
if ($type == "get_trades_market") {

	$cc1 = __get__("currency_code_1");
	$cc2 = __get__("currency_code_2");

	if ($cc1 == $cc2) {
		$result['status'] = 'bad currencies';
	} else {
		$q1 = SQL::get("select currency_code from currency where currency_code = ?",
			[$cc1]);

		$q2 = SQL::get("select currency_code from currency where currency_code = ?",
			[$cc2]);

		if (count($q1) == 0 || count($q2) == 0) {
			$result['status'] = 'bad currencies';
		} else {
			$buy = SQL::get("
				select
					trade_amount / trade_ratio as quantity,
					trade_ratio as price
				from
					trade, wallet as w1, wallet as w2
				where
					w1.wallet_id = wallet_id_from and
					w2.wallet_id = wallet_id_to and
					w1.currency_code = ? and
					w2.currency_code = ? and
					trade_amount > 0
				order by
					trade_ratio
				", [$cc2, $cc1]);

			$sell = SQL::get("
				select
					trade_amount as quantity,
					1 / trade_ratio as price
				from
					trade, wallet as w1, wallet as w2
				where
					w1.wallet_id = wallet_id_from and
					w2.wallet_id = wallet_id_to and
					w1.currency_code = ? and
					w2.currency_code = ? and
					trade_amount > 0
				order by
					trade_ratio
				", [$cc1, $cc2]);

			$result = ['status' => 'ok', 'buy' => $buy, 'sell' => $sell];
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	get_trades_user Daj sve ponude korisnika, uklj. i izvrsene. Quantity
		je kolicina koju zelimo da kupimo (*_to) a price je cena, izrazena
		u *from per *to

	api_key API kljuc korisnika

	{status, ?trades: [{trade_id, quantity, quantity_start, price,
		wallet_id_from, wallet_id_to,
		currency_code_from, currency_code_to, trade_created_date,
		trade_completed_date, trade_cancelled_date}]}
*/
if ($type == "get_trades_user") {

	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];
		$data = SQL::get("
			select
				trade_id,
				trade_amount / trade_ratio as quantity,
				trade_ratio as price,
				wallet_id_from,
				wallet_id_to,
				w1.currency_code as currency_code_from,
				w2.currency_code as currency_code_to,
				trade_created_date,
				trade_completed_date,
				trade_cancelled_date
			from
				trade, wallet as w1, wallet as w2
			where
				w1.wallet_id = wallet_id_from and
				w2.wallet_id = wallet_id_to and
				w1.user_id = ?
			order by
				w1.currency_code, w2.currency_code
			", [$user_id]);

		$result = ['status' => 'ok', 'trades' => $data];
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	get_trades_user_market Daj sve ponude korisnika na odgovarajucem
		marketu, ukljucujuci i izvrsene i otkazane.

	api_key API kljuc korisnika
	currency_code_1 Valuta koja se kupuje
	currency_code_2 Valuta kojom se placa

	{
		status,
		?buy: [{trade_id, quantity, quantity_start, price, wallet_id_from, wallet_id_to,
			trade_created_date, trade_completed_date, trade_cancelled_date}],
		?sell: [{trade_id, quantity, quantity_start, price, wallet_id_from, wallet_id_to,
			trade_created_date, trade_completed_date, trade_cancelled_date}]
	}
*/
if ($type == "get_trades_user_market") {

	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];

		$cc1 = __get__("currency_code_1");
		$cc2 = __get__("currency_code_2");

		if ($cc1 == $cc2) {
			$result['status'] = 'bad currencies';
		} else {
			$q1 = SQL::get("select currency_code from currency where currency_code = ?",
				[$cc1]);

			$q2 = SQL::get("select currency_code from currency where currency_code = ?",
				[$cc2]);

			if (count($q1) == 0 || count($q2) == 0) {
				$result['status'] = 'bad currencies';
			} else {
				$buy = SQL::get("
					select
						trade_id,
						trade_amount / trade_ratio as quantity,
						trade_amount_start / trade_ratio as quantity_start,
						trade_ratio as price,
						wallet_id_from,
						wallet_id_to,
						trade_created_date,
						trade_completed_date,
						trade_cancelled_date
					from
						trade, wallet as w1, wallet as w2
					where
						w1.wallet_id = wallet_id_from and
						w2.wallet_id = wallet_id_to and
						w1.currency_code = ? and
						w2.currency_code = ? and
						w1.user_id = ?
					order by
						trade_created_date desc
					", [$cc2, $cc1, $user_id]);

				$sell = SQL::get("
					select
						trade_id,
						trade_amount as quantity,
						trade_amount_start as quantity_start,
						1 / trade_ratio as price,
						wallet_id_from,
						wallet_id_to,
						trade_created_date,
						trade_completed_date,
						trade_cancelled_date
					from
						trade, wallet as w1, wallet as w2
					where
						w1.wallet_id = wallet_id_from and
						w2.wallet_id = wallet_id_to and
						w1.currency_code = ? and
						w2.currency_code = ? and
						w1.user_id = ?
					order by
						trade_created_date desc
					", [$cc1, $cc2, $user_id]);

				$result = ['status' => 'ok', 'buy' => $buy, 'sell' => $sell];
			}
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	new_trade Kreiraj novu ponudu i okaci je. Ukoliko korisnik
		pokusa da potrosi vise nego sto ima, kolicina koju trosi
		se izjednacava sa onom koju poseduje. Kolicina i cena
		moraju biti pozitivni brojevi, inace se zahtev odbija.

	api_key API kljuc korisnika
	wallet_id_from Odakle se trosi novac
	wallet_id_to Gde ce biti smesteno ono sto se kupuje
	quantity Koliko nameravamo da kupimo
	price Po kojoj ceni kupujemo

	{status, ?trade_id}
*/
if ($type == "new_trade") {

	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];
		$wallet_id_from = __get__("wallet_id_from");
		$wallet_id_to = __get__("wallet_id_to");

		$trade_amount = floatval(__get__("quantity"));
		$trade_ratio = floatval(__get__("price"));

		$trade_amount *= $trade_ratio;

		// verify wallet IDs
		$w1 = SQL::get("select wallet_id, currency_code as cc from wallet where wallet_id = ?
			and user_id = ?", [$wallet_id_from, $user_id]);

		$w2 = SQL::get("select wallet_id, currency_code as cc from wallet where wallet_id = ?
			and user_id = ?", [$wallet_id_to, $user_id]);

		if (count($w1) == 0 || count($w2) == 0) {
			$result['status'] = 'bad wallet_id';
		} else if ($w1[0]['cc'] == $w2[0]['cc']) {
			$result['status'] = 'wallets have the same currency';
		} else {
			$wallet_amount = SQL::get("select wallet_amount as wa from wallet where wallet_id
				= ?", [$wallet_id_from]);

			if ($wallet_amount[0]['wa'] <= 0) {
				$result['status']  = 'wallet is empty';
			} else if ($trade_amount <= 0) {
				$result['status']  = 'quantity must be positive';
			} else if ($trade_ratio <= 0) {
				$result['status']  = 'price must be positive';
			} else {
				$ok = SQL::run("insert into trade(wallet_id_from, wallet_id_to, trade_amount_start,
					trade_ratio, trade_created_date) values (?, ?, ?, ?, now())",
					[$wallet_id_from, $wallet_id_to, $trade_amount, $trade_ratio]);
				
				if ($ok) {
					$trade_id = SQL::last_insert_id();

					$ok = SQL::run("call trade_matcher(?)", [$trade_id]);
					
					if ($ok) {
						$result = ['status' => 'ok', 'trade_id' => $trade_id];
					} else {
						$result = ['status' => 'trade_matcher call failed, notify the admin'];
					}
				} else {
					$result['status'] = 'failed';	
				}
			}
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	cancel_trade Otkazi ponudu i refunduj korisnikov wallet. Ponuda ne
		sme da bude prazna (amount > 0)

	api_key API kljuc korisnika
	trade_id ID ponude

	{status}
*/
if ($type == "cancel_trade") {
	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];

		$trade_id = __get__("trade_id");

		$ok = SQL::get("select trade_id, trade_amount as ta from trade, wallet
			where trade_id = ? and wallet_id_from = wallet_id and user_id = ?",
			[$trade_id, $user_id]);

		if (count($ok) == 0) {
			$result['status'] = 'bad trade_id';
		} else if ($ok[0]['ta'] <= 0) {
			$result['status'] = 'trade is empty';
		} else {
			$ok = SQL::run("call cancel_trade(?)", [$trade_id]);

			if ($ok) {
				$result['status'] = 'ok';
			} else {
				$result['status'] = 'unknown error in cancel_trade()';
			}
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	get_transactions_user Daj sve transakcije korisnika, zajedno sa tipom
		transakcije

	api_key API kljuc korisnika

	{status, ?transactions: [transaction_id, wallet_id, currency_code,
		transaction_delta, transaction_date, transaction_type]}
*/
if ($type == 'get_transactions_user') {
	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];

		$res = SQL::get("
			select
				transaction_id,
				wallet.wallet_id as `wallet_id`,
				currency_code,
				transaction_delta,
				transaction_date,
				if (trade_id_home is null and trade_id_away is null,
					'external transfer',
					if (trade_id_home is not null and trade_id_away is not null,
						'executed trade',
						if (transaction_delta < 0,
							'trade placed',
							'trade cancelled'
						)
					)
				) as transaction_type
			from
				transaction, wallet
			where
				transaction.wallet_id = wallet.wallet_id and
				user_id = ?
			order by
				transaction_id desc", [$user_id]);

		$result = ['status' => 'ok', 'transactions' => $res];
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	wallet_transfer prebaci sa jednog na drugi wallet jednog korisnika,
		ako su oni u istoj valuti. Vaze ista ogranicenja kao za postovanje
		offera, kolicina mora da bude pozitivna i wallet ne sme da postane
		negativan nakon transfera

	api_key API kljuc korisnika
	wallet_id_from Odakle se prebacuje
	wallet_id_to Dokle se prebacuje
	quantity Kolicina koja se prenosi

	{status}
*/
if ($type == 'wallet_transfer') {
	// auth
	$user_id = SQL::get("select user_id from user where user_password = ?",
		[__get__("api_key")]);

	if (count($user_id) == 0) {
		$result['status'] = 'authentication failed';
	} else {
		$user_id = $user_id[0]['user_id'];
		$amt = floatval(__get__('quantity'));

		$w1 = SQL::get("select * from wallet
			where wallet_id = ? and user_id = ?",
			[__get__("wallet_id_from"), $user_id]);

		$w2 = SQL::get("select * from wallet
			where wallet_id = ? and user_id = ?",
			[__get__("wallet_id_to"), $user_id]);

		if (count($w1) == 0 || count($w2) == 0) {
			$result['status'] = 'bad wallet ids';
		} else if ($amt <= 0) {
			$result['status'] = 'quantity must be positive';
		} else if ($w1[0]['wallet_amount'] < $amt) {
			$result['status'] = 'insufficient funds';
		} else if ($w1[0]['currency_code'] != $w2[0]['currency_code']) {
			$result['status'] = 'different currencies';
		} else {
			SQL::run('call credit_wallet(?, ?, null, null)', [$w1[0]['wallet_id'], -$amt]);
			SQL::run('call credit_wallet(?, ?, null, null)', [$w2[0]['wallet_id'], $amt]);
			$result['status'] = 'ok';
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	super_credit_wallet Dodaj (ili oduzmi) kolicinu u neki wallet.
		Posle izmene moguce je i da kolicina u walletu bude negativna

	super_key Kljuc za super funkcije
	wallet_id ID walleta koji se menja
	quantity Za koliko se menja

	{status}
*/
if ($type == 'super_credit_wallet') {
	if ($super_key != __get__('super_key')) {
		$result['status'] = 'authentication failed';
	} else {
		$wid = SQL::get('select wallet_id from wallet where wallet_id = ?',
			[__get__('wallet_id')]);
		$amt = floatval(__get__('quantity'));

		if (count($wid) == 0) {
			$result['status'] = 'bad wallet id';
		} else {
			SQL::run('call credit_wallet(?, ?, null, null)', [$wid[0]['wallet_id'], $amt]);
			$result['status'] = 'ok';
		}
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	super_get_users Daj podatke o svim korisnicima

	super_key Kljuc za super funkcije

	{status, ?users: [{user_id, user_email, user_password}]}
*/
if ($type == 'super_get_users') {
	if ($super_key != __get__('super_key')) {
		$result['status'] = 'authentication failed';
	} else {
		$result = ['status' => 'ok', 'users' => SQL::get('select * from user', [])];
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	super_get_wallets Daj podatke o svim walletima

	super_key Kljuc za super funkcije

	{status, ?wallets: [{wallet_id, user_id, currency_code, wallet_amount, wallet_name}]}
*/
if ($type == 'super_get_wallets') {
	if ($super_key != __get__('super_key')) {
		$result['status'] = 'authentication failed';
	} else {
		$result = ['status' => 'ok', 'wallets' => SQL::get('select * from wallet', [])];
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	super_get_trades Daj podatke o svim ponudama. trade_amount je kolicina koju
		podnosilac namerava da potrosi, a ne koju namerava da kupi.

	super_key Kljuc za super funkcije

	{status, ?trades: [{trade_id, wallet_id_from, wallet_id_to,
		trade_amount_start, trade_amount, trade_ratio,
		trade_created_date, trade_completed_date, trade_cancelled_date}]}
*/
if ($type == 'super_get_trades') {
	if ($super_key != __get__('super_key')) {
		$result['status'] = 'authentication failed';
	} else {
		$result = ['status' => 'ok', 'trades' => SQL::get('select * from trade', [])];
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	super_get_transactions Daj podatke o svim transakcijama. trade_id_home i trade_id_away
		su id-evi ponuda vezanih za tu transakciju. Ako su oba null, radi se o eksternom
		transferu ili wallet transferu. Ako je jedno null, onda se radi o postavljanju ili
		otkazivanju ponude. Ako su oba ne-null, radi se o delimicno ili potpuno izvrsenoj
		trgovini.

	{status, ?transactions: [transaction_id, wallet_id, transaction_delta, trade_id_home,
		trade_id_away, transaction_date]}
*/
if ($type == 'super_get_transactions') {
	if ($super_key != __get__('super_key')) {
		$result['status'] = 'authentication failed';
	} else {
		$result = ['status' => 'ok', 'transactions' => SQL::get('select * from transaction', [])];
	}

	echo json_encode_utf8($result);
	exit();
}

/*
	super_new_currency Dodaj novu valutu.

	super_key Kljuc za super funkcije
	currency_code Kod valute, 1-3 karaktera
	currency_name Ime valute

	{status}
*/
if ($type == 'super_new_currency') {
	if ($super_key != __get__('super_key')) {
		$result['status'] = 'authentication failed';
	} else {
		$cc = __get__('currency_code');
		$cn = __get__('currency_name');

		if (strlen($cc) == 0) {
			$result['status'] = 'empty currency code';
		} else if (strlen($cn) == 0) {
			$result['status'] = 'empty currency name';
		}

		$ok = SQL::run('insert into currency(currency_code, currency_name)
			values (?, ?)', [$cc, $cn]);

		if ($ok) {
			$result['status'] = 'ok';
		} else {
			$result['status'] = 'failed';
		}
	}

	echo json_encode_utf8($result);
	exit();
}
