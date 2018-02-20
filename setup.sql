# neko ime koje nece da se poklapa ni sa cim drugim
create database is43bt;
use is43bt;

/* tabela za korisnike, vrlo jednostavno */
create table user (
	user_id int primary key not null auto_increment,
	user_email varchar(45) not null,
	user_password varchar(256) not null,

	unique index (user_email)
);

/* nema fee-ova, kod nas je sve dzabe! */

/* 
	Valute dostupne za trading.
*/
create table currency (
	currency_code varchar(3) primary key not null,

	currency_name varchar(45) not null
);

create table wallet (
	wallet_id int primary key not null auto_increment,
	wallet_name varchar(45) not null,

	currency_code varchar(3) not null,
	
	foreign key (currency_code)
		references currency(currency_code)
		on update cascade
		on delete restrict,

	wallet_amount double not null,

	user_id int not null,

	foreign key (user_id)
		references user(user_id)
		on update cascade
		on delete restrict
);

/*
	Trade oznacava zelju da se kupi odredjena valuta.
	trade_currency je druga valuta (ono sto se kupuje).
	Valuta kojom se placa se dobija iz wallet-a.
	Postovanjem tradeova upravlja trigger
*/
create table trade (
	trade_id int primary key not null auto_increment,
	
	wallet_id_from int not null,
	wallet_id_to int not null,

	foreign key (wallet_id_from)
		references wallet(wallet_id)
		on update cascade
		on delete restrict,

	foreign key (wallet_id_to)
		references wallet(wallet_id)
		on update cascade
		on delete restrict,

	trade_amount_start double not null,
	trade_amount double not null default 0,
	trade_ratio double not null,

	trade_created_date datetime not null,
	trade_completed_date datetime,
	trade_cancelled_date datetime
);

/*
	Tabela koja cuva sve izvrsene transakcije odnosno promene stanja wallet-a
*/
create table transactions (
	transaction_id int primary key not null auto_increment,

	wallet_id int not null,
	transaction_delta double not null,

	trade_id_home int,
	trade_id_away int,

	foreign key (wallet_id)
		references wallet(wallet_id)
		on update cascade
		on delete restrict

	/* nije strani kljuc zbog nesrecnog dizajna mehanizma trigera */

	/*

	foreign key (trade_id_home)
		references trade(trade_id)
		on update cascade
		on delete restrict,

	foreign key (trade_id_away)
		references trade(trade_id)
		on update cascade
		on delete restrict

	*/
);

/* password hasher */
delimiter //
create function pwhash(x text)
returns varchar(64)
begin
	return sha2(x, 256);
end //
delimiter ;

/* full salted hasher */
delimiter //
create function fullhash(x text, y text)
returns varchar(64)
begin
	return pwhash(concat(x, '@@#', y, 'pof4dsa1pof5aopf23of5kafko31kfda'));
end //
delimiter ;

delimiter //
create function get_currency_from(t_id int)
returns varchar(3)
begin
	return (select min(currency_code) from wallet, trade
		where wallet.wallet_id = trade.wallet_id_from
		and trade.trade_id = t_id);
end //
delimiter ;

delimiter //
create function get_currency_to(t_id int)
returns varchar(3)
begin
	return (select min(currency_code) from wallet, trade
		where wallet.wallet_id = trade.wallet_id_to
		and trade.trade_id = t_id);
end //
delimiter ;

/* procedura za kreditovanje wallet-a */
delimiter //
create procedure credit_wallet(
	w_id int, delta double, t_id_home int, t_id_away int)
begin
	update wallet set wallet_amount = wallet_amount + delta
		where wallet_id = w_id;

	/* insert u tabelu izvrsenih transakcija */
	insert into transactions(wallet_id, transaction_delta, trade_id_home, trade_id_away)
		values (w_id, delta, t_id_home, t_id_away);
end //
delimiter ;

/* procedura za otkazivanje trade-a, refunduj from wallet */
delimiter //
create procedure cancel_trade(t_id int)
begin

	declare w_id int;
	declare t_am double;

	set w_id = (select wallet_id_from from trade where trade_id = t_id);
	set t_am = (select trade_amount from trade where trade_id = t_id);

	call credit_wallet(w_id, t_am, t_id, null);

	update trade set
		trade_amount = 0,
		trade_cancelled_date = now()
	where trade_id = t_id;

end //
delimiter ;

/* trigger koji proverava trade koji treba da se ubaci */
delimiter //
create trigger trade_insert_trigger
before insert on trade
for each row
begin

	declare w_avail double;

	set w_avail = (select wallet_amount from wallet where wallet_id = new.wallet_id_from);

	set new.trade_amount = new.trade_amount_start;

	if new.trade_amount > w_avail
	then
		set new.trade_amount = w_avail;
	end if;

	call credit_wallet(new.wallet_id_from, -new.trade_amount, new.trade_id, null);

end //
delimiter ;

delimiter //
create procedure trade_matcher(in new_trade_id int)
begin
	declare break_condition int;

	declare this_ratio double;
	declare that_ratio double;
	declare this_amount double;
	declare that_amount double;

	declare mean_ratio double;

	declare that_wallet_from int;
	declare that_wallet_to int;

	declare rows int;
	declare row_id int;

	declare new_trade_ratio double;
	declare new_trade_amount double;
	declare new_wallet_id_from int;
	declare new_wallet_id_to int;

	set new_trade_ratio = (select trade_ratio from trade where trade_id = new_trade_id);
	set new_trade_amount = (select trade_amount from trade where trade_id = new_trade_id);
	set new_wallet_id_from = (select wallet_id_from from trade where trade_id = new_trade_id);
	set new_wallet_id_to = (select wallet_id_to from trade where trade_id = new_trade_id);

	set break_condition = 0;

	while break_condition = 0
	do
		# pronadji najbolji offer sa druge strane, ako postoji
		set rows = (select count(*) from trade as t1 where
			get_currency_from(trade_id) = get_currency_to(new_trade_id)
			and
			get_currency_to(trade_id) = get_currency_from(new_trade_id)
			and
			trade_amount > 0
		);

		if rows > 0
		then
			set row_id = 
			(
				select min(trade_id) from trade where
					get_currency_from(trade_id) = get_currency_to(new_trade_id)
					and
					get_currency_to(trade_id) = get_currency_from(new_trade_id)
					and
					trade_amount > 0
				order by
					trade_ratio desc,
					trade_created_date asc
			);

			set this_ratio = new_trade_ratio;
			set that_ratio = (select trade_ratio
				from trade where trade_id = row_id);

			set this_amount = new_trade_amount;
			set that_amount = (select trade_amount
				from trade where trade_id = row_id);

			set mean_ratio = sqrt(this_ratio / that_ratio);

			set that_wallet_from = (select wallet_id_from from trade where trade_id = row_id);
			set that_wallet_to = (select wallet_id_to from trade where trade_id = row_id);

			# ukoliko mogu da se upare, upari ih
			if this_ratio * that_ratio >= 1.0
			then
				if (this_amount > that_amount * mean_ratio)
				then
					# this prezivljava, nije potpuno sparen
					# that je completed
					call credit_wallet(new_wallet_id_to, that_amount,
						new_trade_id, row_id);

					call credit_wallet(that_wallet_to, that_amount * mean_ratio,
						row_id, new_trade_id);

					update trade set
						trade_completed_date = now(),
						trade_amount = 0.0
					where trade_id = row_id;

					update trade set
						trade_amount = trade_amount - that_amount * mean_ratio
					where trade_id = new_trade_id;

				elseif (this_amount < that_amount * mean_ratio)
				then

					call credit_wallet(new_wallet_id_to, this_amount / mean_ratio,
						new_trade_id, row_id);

					call credit_wallet(that_wallet_to, this_amount,
						row_id, new_trade_id);

					update trade set
						trade_completed_date = now(),
						trade_amount = 0.0
					where trade_id = new_trade_id;

					update trade set
						trade_amount = trade_amount - this_amount / mean_ratio
					where trade_id = row_id;

					set break_condition = 1;
				else

					call credit_wallet(new_wallet_id_to, this_amount / mean_ratio,
						new_trade_id, row_id);

					call credit_wallet(that_wallet_to, this_amount,
						row_id, new_trade_id);

					update trade set
						trade_completed_date = now(),
						trade_amount = 0.0
					where trade_id = new_trade_id or trade_id = row_id;

					set break_condition = 1;
				end if;

			else
				set break_condition = 1;
			end if;
		else
			set break_condition = 1;
		end if;
	end while;
end //
delimiter ;

/* Event koji zove proceduru za svaki red */
delimiter //
create event trade_event
on schedule every 5 second
on completion preserve
enable
do begin

	declare done int;
	declare trade_id int;
	declare
		cur cursor
	for
		select trade_id from trade order by trade_created_date;

	declare continue handler for not found set done = 1;

	open cur;

	set done = 0;

	mainLoop: loop
		fetch cur into trade_id;
		if done = 1
		then
			leave mainLoop;
		end if;
		call trade_matcher(trade_id);
	end loop mainLoop;

	close cur;

end //
delimiter ;

/* Useri i permisije */
drop user 'trader'@'localhost';
create user 'trader'@'localhost' identified by '0security';
grant all privileges on is43bt.* to 'trader'@'localhost';
