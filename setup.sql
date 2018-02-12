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

	trade_amount double not null,
	trade_ratio double not null,

	trade_created_date datetime not null,
	trade_completed_date datetime
	# null completed date znaci nije completed
);

delimiter //
create function get_base_currency(trade_id int)
returns varchar(3)
begin
	return (select currency_code from wallet, trade
		where wallet.wallet_id = trade.wallet_id);
end //
delimiter ;

/* procedura za kreditovanje wallet-a */
delimiter //
create procedure credit_wallet(w_id int, delta double)
begin
	update wallet set amount = amount + delta
		where wallet_id = w_id;
end //
delimiter ;

delimiter //
create trigger trade_trigger
after insert on trade
for each row
begin
	declare break_condition int;
	
	declare base_currency varchar(3);

	declare this_ratio double;
	declare that_ratio double;
	declare this_amount double;
	declare that_amount double;

	declare mean_ratio double;

	declare that_wallet int;

	set break_condition = 0;
	set base_currency = get_base_currency(new.trade_id);

	# pronadji najbolji offer sa druge strane, ako postoji
	set @rows = (select count(*) from trade where
		trade_currency_code = @base_currency
		and
		get_base_currency(trade_id)
			= new.trade_currency_code
		and
		trade_completed_date is null
	);

	if @rows > 0
	then
		set @row_id = 
		(
			select min(trade_id) from trade where
				trade_currency_code = @base_currency
				and
				get_base_currency(trade_id)
					= new.trade_currency_code
				and
				trade_completed_date is null
			order by 
				trade_ratio desc,
				trade_created_date asc
		);

		

		set this_ratio = new.trade_ratio;
		set that_ratio = (select trade_ratio
			from trade where trade_id = @row_id);

		set this_amount = new.trade_amount;
		set that_amount = (select trade_amount
			from trade where trade_id = @row_id);

		set mean_ratio = sqrt(this_ratio * that_ratio);

		# ukoliko mogu da se upare, upari ih
		if this_ratio * that_ratio >= 1.0
		then
			if this_amount > that_amount
			then
				# this prezivljava, nije potpuno sparen
				credit_wallet(new.wallet_id, that_amount)

			end if;

		end if;

	end if;
end //
delimiter ;