# Plan: Spot-Type Kategorien anpassen

## Context

Die spot_type-Kategorien "Wasser" und "Verein" werden kaum genutzt und sollen entfernt werden.
Zwei neue Typen "Wald" und "Windpark" kommen hinzu. Gebirge bleibt.
Spots mit "Windpark" im Namen sollen automatisch in die neue Kategorie "Windpark" migriert werden.
Bestehende Wasser/Verein-Spots existieren nicht (vom User bestätigt), daher keine Datenmigration nötig.

Neue ENUM: Bando, Feld, Gebirge, Park, Wald, Windpark, Sonstige
Entfernt: Verein, Wasser
Neu: Wald, Windpark
Farben: Wald #2d6a4f (dunkelgrün), Windpark #74c2e0 (hellblau)

## Ausführung
### Schritt 1: SQL-Migration (direkt auf DB ausführen)

1. Neue Typen zur ENUM hinzufügen (Wasser/Verein noch behalten)
ALTER TABLE spots MODIFY spot_type ENUM('Bando','Feld','Gebirge','Park','Verein','Wasser','Wald','Windpark','Sonstige') NOT NULL;

2. Spots mit "Windpark" im Namen migrieren
UPDATE spots SET spot_type = 'Windpark' WHERE name LIKE '%Windpark%';

3. ENUM finalisieren (Verein + Wasser entfernen)
ALTER TABLE spots MODIFY spot_type ENUM('Bando','Feld','Gebirge','Park','Wald','Windpark','Sonstige') NOT NULL;

### Schritt 2: database.sql aktualisieren

Datei: database.sql
ENUM-Definition in der spots-Tabelle anpassen:
spot_type ENUM('Bando','Feld','Gebirge','Park','Wald','Windpark','Sonstige') NOT NULL,

### Schritt 3: PHP-Validierungs-Arrays aktualisieren (5 Dateien)

In allen folgenden Dateien $allowedTypes anpassen:
┌─────────────────────────────────────────────────────────────┬───────┐
│                            Datei                            │ Zeile │
├─────────────────────────────────────────────────────────────┼───────┤
│ fpv-spots-germany.de/public/php/api/spots.php               │ ~74   │
├─────────────────────────────────────────────────────────────┼───────┤
│ fpv-spots-germany.de/public/php/api/spot.php                │ ~105  │
├─────────────────────────────────────────────────────────────┼───────┤
│ fpv-spots-germany.de/public/php/api/save_legend.php         │ ~19   │
├─────────────────────────────────────────────────────────────┼───────┤
│ fpv-spots-germany.de/private/php/spots/spot_submit.php      │ ~41   │
├─────────────────────────────────────────────────────────────┼───────┤
│ fpv-spots-germany.de/private/php/spots/edit_spot_submit.php │ ~53   │
└─────────────────────────────────────────────────────────────┴───────┘

$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];

### Schritt 4: Frontend-Dropdowns aktualisieren (2 Dateien):

fpv-spots-germany.de/index.php (Spot-Erstellen-Formular, ~263):
<option value="Bando">Bando</option>
<option value="Feld">Feld</option>
<option value="Gebirge">Gebirge</option>
<option value="Park">Park</option>
<option value="Wald">Wald</option>
<option value="Windpark">Windpark</option>
<option value="Sonstige">Sonstige</option>

fpv-spots-germany.de/public/php/spots/edit_spot.php (~90):
foreach (['Bando','Feld','Gebirge','Park','Wald','Windpark','Sonstige'] as $t):

### Schritt 5: Farb-Mappings aktualisieren (4 Dateien)

fpv-spots-germany.de/index.php (~99) — PHP Legend

$typeColors = [
    'Bando'=>'#4b4e5a', 'Feld'=>'#ffe224', 'Gebirge'=>'#b1602d',
    'Park'=>'#3f9826', 'Wald'=>'#2d6a4f', 'Windpark'=>'#74c2e0', 'Sonstige'=>'#ffffff'
];

fpv-spots-germany.de/public/js/map.js (~90) — JS TYPE_COLORS

const TYPE_COLORS = {
    'Bando':    '#4b4e5a',
    'Feld':     '#ffe224',
    'Gebirge':  '#b1602d',
    'Park':     '#3f9826',
    'Wald':     '#2d6a4f',
    'Windpark': '#74c2e0',
    'Sonstige': '#ffffff',
};
TYPE_TEXT_COLORS: Wald und Windpark brauchen keinen Override (weiße Schrift passt).

fpv-spots-germany.de/public/php/spots/spot_detail.php (~99)

$typeColors = [
    'Bando'=>'#4b4e5a', 'Feld'=>'#ffe224', 'Gebirge'=>'#b1602d',
    'Park'=>'#3f9826', 'Wald'=>'#2d6a4f', 'Windpark'=>'#74c2e0', 'Sonstige'=>'#ffffff',
];

fpv-spots-germany.de/public/php/account/profile.php — JS TYPE_COLORS (inline)

Gleiche Anpassung wie map.js.

## Verifikation

1. DB-Migration ausführen, prüfen: SELECT spot_type, COUNT(*) FROM spots GROUP BY spot_type;
2. Spot erstellen → Dropdown zeigt 7 Typen (ohne Wasser/Verein, mit Wald/Windpark)
3. Windpark-Spot erstellen → erscheint in hellblauer Farbe auf Karte
4. Legende filtert korrekt nach allen 7 neuen Typen
5. Bestehende Windpark-Spots (per Name) haben nun Typ "Windpark"
