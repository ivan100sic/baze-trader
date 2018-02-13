use is43bt;

insert into user(user_id, user_email, user_password) values
	(1, 'ivan100sic@gmail.com', pwhash('1234')),
	(2, 'vukasinsta@gmail.com', pwhash('5678')),
	(3, 'miljanrist@gmail.com', pwhash('9012'))
;

insert into currency(currency_code, currency_name) values
	('RSD', 'Srpski dinar'),
	('PMF', 'PMF kredit'),
	('EFK', 'Kredit za Elektronski fakultet'),
	('MNZ', 'Obrok u menzi')
;

insert into wallet(wallet_id, wallet_name, currency_code, wallet_amount, user_id) values
	(1, 'Pun sam s pare', 'RSD', 0, 1),
	(2, 'PMF moja druga kuca', 'PMF', 0, 1),

	(3, 'Moj dinarski racun', 'RSD', 0, 2),
	(4, 'PMF uplate', 'PMF', 0, 2),
	(5, 'Menza is love menza is life', 'MNZ', 0, 2),

	(6, 'Pare za u grad', 'RSD', 0, 3),
	(7, 'Pare za kola', 'RSD', 0, 3),
	(8, 'Menza', 'MNZ', 0, 3),

	(9, 'Lepsa je hrana kod kuce', 'MNZ', 0, 1)
;

call credit_wallet(1, 500000, null, null);
call credit_wallet(2, 500, null, null);

call credit_wallet(3, 200000, null, null);
call credit_wallet(4, 2000, null, null);
call credit_wallet(5, 60, null, null);

call credit_wallet(6, 10000, null, null);
call credit_wallet(7, 8000, null, null);
call credit_wallet(8, 4, null, null);

/* Sada Vukasin hoce da proda 10 obroka za menzu za 69 din/obroku */
insert into trade(trade_id, wallet_id_from, wallet_id_to, trade_amount_start, trade_ratio, trade_created_date) values
	(1, 5, 3, 10, 1.0 / 69.0, now());

call trade_matcher(1);

/* Stosic hoce da kupi 2 obroka ali samo po ceni od 50 rsd / obrok */
insert into trade(trade_id, wallet_id_from, wallet_id_to, trade_amount_start, trade_ratio, trade_created_date) values
	(2, 1, 9, 100, 50.0, now());

call trade_matcher(2);

/* Miljan hoce da kupi dva obroka za 70 dinara, i tako roba nadje svog kupca */
insert into trade(trade_id, wallet_id_from, wallet_id_to, trade_amount_start, trade_ratio, trade_created_date) values
	(3, 6, 8, 150, 75.0, now());

call trade_matcher(3);

/* Stosic se povampirio i odlucio da hoce da ide u menzu za 100 rsd / obrok ceo zivot */
insert into trade(trade_id, wallet_id_from, wallet_id_to, trade_amount_start, trade_ratio, trade_created_date) values
	(4, 1, 9, 9999999, 100.0, now());

call trade_matcher(4);

/* Stosic se predomislio, otkazuje narudzbu */

call cancel_trade(4);

select * from user;
select * from currency;
select * from wallet;
select * from transactions;
select * from trade;
