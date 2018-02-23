# Trader - Projekat iz predmeta "Baze podataka"

## Uvod

Cilj projekta jeste da se napravi backend aplikacije koja bi služila
za trgovinu valutama, hartijama od vrednosti, ili u suštini bilo čega.
Aplikacija se sastoji od baze, odnosno njene definicije koja se nalazi
u fajlu `setup.sql`, i API-ja (interfejsa za programiranje aplikacija)
preko kojeg korisnici mogu da izvršavaju upite. API se sastoji iz dva
dela, jednog koji je namenjen krajnjim korisnicima i drugog koji je
namenjen isključivo administratoru aplikacije (super korisniku) odnosno
nekoj pomoćnoj skripti koja prati njeno izvršavanje. 

## Instalacija

Potrebno je imati instaliran PHP 7 i MySQL 5.7. Dovoljno je izvršiti
na SQL serveru sadržaj fajla `setup.sql`. Ukoliko je potrebna nova
instalacija (brišu se svi podaci), pokrenuti sadržaj fajla `clean.sql`
a zatim `setup.sql`. Kreira se baza čiji je naziv `is43bt` i korisnik
`trader@localhost` preko kojeg PHP skripta pristupa bazi. Kreiraju se
neophodne tabele i uskladištene funkcije, procedure, trigeri i eventi.
Potrebno je i da PHP verzija ima učitane module za pristup bazi kao i
modul za konverziju PHP nizova u JSON format. Ovi moduli obično dolaze
uz PHP instalaciju. Ukoliko PHP skripte ne rade, verovatno je PHP verzija
manja od 7 ili neki od modula nije ubačen.

## Korišćenje

Kao što je već rečeno, aplikacija ne servira web stranice već samo
prima i odgovara na upite, preko API-ja. API je implementiran i dokumentovan
u fajlu `www/api.php`. Tu se mogu naći opisi raspoloživih funkcija kao
i opisi njihovih parametara i rezultata koje vraćaju. Ideja je da korisnici
ne trguju "ručno", već pisanjem algoritama koji će to raditi umesto njih.
Samim tim aplikacija i nije dizajnirana sa ciljem da njeni korisnici budu
ljudi, već trading botovi.

### Primer

Pozivom funkcije `new_user` kreira se novi korisnik. Funkcija vraća API ključ
(u suštini hashovanu vrednost e-maila i date šifre) pomoću kojeg se za većinu
ostalih funkcija obavljaju pozivi. Primer URL-a:

```
api.php?type=new_user&user_email=test@test.org&user_password=pwddd
```

Ukoliko sve protekne kako treba, odgovor servera bio bi sledeći JSON objekat:

```
{"status":"ok","api_key":"1b41d7c928f489bf5475cd4d90157567ab8c23f6b510f63e6ffafdd104438207"}
```

Dalje, da bi korisnik mogao da trguje, mora da napravi novčanik (wallet). Pre
ovoga potrebno je da super-korisnik doda definicije valuta. To je moguće uraditi
preko sledećih API poziva. Dodaju se Evro, Dolar i Dinar. Ovaj korisnik "otključava"
super-funkcije pomoću svog super-ključa čija se definicija nalazi u fajlu `api.php`.

```
api.php?super_key=flawle3s3s3s3ecurity&type=super_new_currency&currency_code=RSD&currency_name=Srpski%20dinar
api.php?super_key=flawle3s3s3s3ecurity&type=super_new_currency&currency_code=USD&currency_name=Ameri%C4%8Dki%20dolar
api.php?super_key=flawle3s3s3s3ecurity&type=super_new_currency&currency_code=EUR&currency_name=Evro
```

Zatim korisnik može da otvori dinarski račun

```
api.php?type=new_wallet&api_key=1b41d7c928f489bf5475cd4d90157567ab8c23f6b510f63e6ffafdd104438207&currency_code=RSD&wallet_name=Dinarski%20ra%C4%8Dun
api.php?type=new_wallet&api_key=1b41d7c928f489bf5475cd4d90157567ab8c23f6b510f63e6ffafdd104438207&currency_code=EUR&wallet_name=Devizni%20ra%C4%8Dun
```

Nakon ovoga potrebno je da se u ovaj novčanik smesti neka vrednost. Direktne
izmene vrednosti korisničkih novčanika ne mogu da rade sami korisnici, ali tzv.
super korisnik može. Ovaj korisnik "otključava" super-funkcije pomoću svog
super-ključa čija se definicija nalazi u fajlu `api.php`.

```
api.php?super_key=flawle3s3s3s3ecurity&type=super_credit_wallet&wallet_id=1&quantity=1500
```

Nakon toga korisnik može postaviti svoju ponudu.

```
api.php?type=new_trade&api_key=1b41d7c928f489bf5475cd4d90157567ab8c23f6b510f63e6ffafdd104438207&wallet_id_from=1&wallet_id_to=2&quantity=10&price=99
```

Korisnik može da lista ponude i bez API ključa. Korisnik bi sada trebalo da vidi ponudu
osobe koja želi da kupi 10 evra po ceni od 99 dinara za jedan evro.

```
api.php?type=get_trades_market&currency_code_1=EUR&currency_code_2=RSD
```

Sve ponude se vide i na recipročnom marketu, npr. videćemo ponudu da se proda
990 dinara po ceni od 0.0101... evra za jedan dinar, što je u suštini ista
ponuda kao prethodna, samo posmtrano iz drugačijeg ugla.

```
api.php?type=get_trades_market&currency_code_1=RSD&currency_code_2=EUR
```
