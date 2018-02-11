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

/* tabela koja ce cuvati informaciju o trenutnoj
tarifi u svom jedinom redu */
create table fee (
	fee double not null
);

/* odmah ubacujemo default vrednost */
insert into fee(fee) values (0.0016);

/* procedura za postavljanje fee-a */
delimiter //
create procedure set_fee(in new_fee double)
begin
	update fee set fee = new_fee;
end //
delimiter ;

/* 
	Valute dostupne za trading.
	
	Ogranicavamo da currency code mora da se 
	sastoji od tri slova
*/
create table currency (
	currency_code varchar(3) primary key not null
		check (currency_code rlike "^[A-Z]+$"),

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
	
	wallet_id int not null,

	foreign key (wallet_id)
		references wallet(wallet_id)
		on update cascade
		on delete restrict,

	trade_currency_code varchar(3) not null,

	foreign key (trade_currency_code)
		references currency(currency_code)
		on update cascade
		on delete restrict,

	amount double not null,
	ratio double not null
);

