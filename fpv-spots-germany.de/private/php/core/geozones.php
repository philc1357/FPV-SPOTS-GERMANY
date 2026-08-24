<?php
declare(strict_types=1);
// =============================================================
// Geozonen – zentraler Zonenkatalog und DIPUL-Anbindung
//
// Einzige Quelle der Wahrheit fuer:
//   a) die Buendelung der 34 DIPUL-WMS-Layer auf 5 Kartengruppen
//   b) die Bewertungsmatrix (Zonentyp x Betriebsklasse -> Ampel)
//   c) die Pflichten-Checkliste je Betriebsklasse
//   d) die WFS-Abfrage fuer den Spot-Check
//
// Datenquelle: DIPUL der Deutschen Flugsicherung (DFS).
// Lizenz CC BY-ND 4.0 – Kacheln unveraendert darstellen,
// Quellenangabe "Quelle Geodaten: DFS, BKG" ist Pflicht.
//
// WICHTIG – rechtliche Grundhaltung dieses Katalogs:
// Es gibt bewusst KEINE Ampelstufe "gruen/erlaubt". Die guenstigste
// Aussage lautet "keine Zone in unseren Daten gefunden". Eine
// Fehlentwarnung ist das einzige echte Haftungsrisiko des Features;
// ein zu strenger Hinweis kostet den Piloten nur einen Blick auf
// dipul.de. Wo der Gesetzeswortlaut Ausnahmen zulaesst, die hier
// nicht zweifelsfrei nachgebildet werden koennen, wird die Zone
// als eingeschraenkt markiert und auf die amtliche Quelle verwiesen.
// =============================================================

// -------------------------------------------------------------
// Endpunkte
// -------------------------------------------------------------
const DIPUL_WMS_URL = 'https://uas-betrieb.dfs.de/geoservices/dipul/wms';
const DIPUL_WFS_URL = 'https://uas-betrieb.dfs.de/geoservices/dipul/wfs';
const DIPUL_ATTRIBUTION = 'Quelle Geodaten: DFS, BKG';
const DIPUL_INFO_URL = 'https://www.dipul.de/homepage/de/informationen/geografische-gebiete/';

// -------------------------------------------------------------
// a) Karten-Buendel: 34 DIPUL-Layer auf 5 verstaendliche Gruppen
//
// "temporaer" ist bewusst ein eigenes Buendel und nicht Teil von
// "luftverkehr": temporaere Betriebseinschraenkungen aendern sich
// taeglich und brauchen deshalb eine sehr kurze Cache-Zeit. Waeren
// sie im grossen Luftverkehrs-Buendel, muesste entweder das ganze
// Buendel staendig neu geladen werden oder die kurzlebigen Sperren
// waeren veraltet – beides schlecht.
// -------------------------------------------------------------
const DIPUL_BUNDLES = [
    'luftverkehr' => [
        'flughaefen',
        'flugplaetze',
        'kontrollzonen',
        'flugbeschraenkungsgebiete',
        'haengegleiter',
        'modellflugplaetze',
    ],
    'naturschutz' => [
        'naturschutzgebiete',
        'nationalparks',
        'vogelschutzgebiete',
        'ffh-gebiete',
    ],
    'siedlung' => [
        'wohngrundstuecke',
        'freibaeder',
        'krankenhaeuser',
        'justizvollzugsanstalten',
        'behoerden',
        'diplomatische_vertretungen',
        'internationale_organisationen',
        'polizei',
        'sicherheitsbehoerden',
        'labore',
        'militaerische_anlagen',
    ],
    'infrastruktur' => [
        'bundesautobahnen',
        'bundesstrassen',
        'bahnanlagen',
        'binnenwasserstrassen',
        'seewasserstrassen',
        'schifffahrtsanlagen',
        'stromleitungen',
        'umspannwerke',
        'kraftwerke',
        'industrieanlagen',
        'windkraftanlagen',
    ],
    'temporaer' => [
        'temporaere_betriebseinschraenkungen',
    ],
];

// Anzeigenamen der Buendel fuer die Layer-Kontrolle der Karte
const DIPUL_BUNDLE_LABELS = [
    'luftverkehr'   => 'Luftverkehr',
    'naturschutz'   => 'Naturschutz',
    'siedlung'      => 'Siedlung & Behörden',
    'infrastruktur' => 'Infrastruktur',
    'temporaer'     => 'Temporäre Sperrungen',
];

// Cache-Dauer je Buendel in Sekunden (siehe Kommentar oben)
const DIPUL_BUNDLE_TTL = [
    'luftverkehr'   => 86400,
    'naturschutz'   => 86400,
    'siedlung'      => 86400,
    'infrastruktur' => 86400,
    'temporaer'     => 600,
];

// -------------------------------------------------------------
// Betriebsklassen des Piloten
//
// Drei Stufen, an der realen FPV-Landschaft ausgerichtet. Die
// EU-Betriebskategorien A1/A2/A3 stehen dahinter, werden dem Nutzer
// aber nicht als Jargon vorgesetzt.
//
// Default ist bewusst "a3" – die konservativste Annahme und zugleich
// der haeufigste Fall (jeder Eigenbau ab 250 g ohne C-Klasse).
// -------------------------------------------------------------
const PILOT_CLASS_DEFAULT = 'a3';

const PILOT_CLASSES = [
    'u250' => [
        'label' => 'unter 250 g',
        'hint'  => 'Tinywhoop, 2–3 Zoll',
        'eu'    => 'C0 bzw. Eigenbau unter 250 g – Unterkategorie A1',
    ],
    'c1c2' => [
        'label' => 'C1/C2 mit Klasse',
        'hint'  => 'Avata, Neo, Cinewhoop mit C-Label',
        'eu'    => 'Klassifiziert C1 (unter 900 g) oder C2 (unter 4 kg) – A1 bzw. A2',
    ],
    'a3' => [
        'label' => 'ab 250 g Eigenbau',
        'hint'  => '5 Zoll, Freestyle, Cinelifter',
        'eu'    => 'Eigenbau ab 250 g, C3 oder C4 – Unterkategorie A3',
    ],
];

/** Prueft einen Klassen-Bezeichner gegen die Whitelist, sonst Default */
function pilot_class_valid(?string $class): string
{
    return isset(PILOT_CLASSES[$class]) ? $class : PILOT_CLASS_DEFAULT;
}

/** Liest die Betriebsklasse aus dem Cookie (kein Login noetig) */
function pilot_class_from_cookie(): string
{
    return pilot_class_valid($_COOKIE['pilot_class'] ?? null);
}

// -------------------------------------------------------------
// b) Bewertungsmatrix: Zonentyp -> Ampel + Erklaerung
//
// Aufbau je Eintrag:
//   label     Anzeigename der Zone
//   paragraf  Fundstelle im Gesetz
//   ampel     'rot'  = Betrieb grundsaetzlich untersagt / Zustimmung zwingend
//             'gelb' = Betrieb nur unter Bedingungen
//             'info' = kein Verbot, aber relevant fuer die Flugplanung
//   text      Erklaerung fuer alle Betriebsklassen
//   klassen   Optionale Ueberschreibung fuer einzelne Betriebsklassen
//
// Die Ampel ist bei den meisten Zonen bewusst gewichtsUNabhaengig –
// das ist keine Vereinfachung, sondern die Rechtslage: § 21h LuftVO
// kennt eine Startmassen-Ausnahme nur bei Wohngrundstuecken (Nr. 7),
// und die verlangt zusaetzlich Kamera- und Funkfreiheit. Die fuer FPV
// entscheidende Gewichtsgrenze steckt stattdessen in der EU-Betriebs-
// kategorie A3 (150 m Abstand zu Wohn- und Gewerbegebieten) – siehe
// die Pseudo-Zone 'a3_abstand' am Ende der Liste.
// -------------------------------------------------------------
const ZONE_RULES = [
    // --- Naturschutz: fuer Hobbyflug gewichtsunabhaengig gesperrt ---
    'naturschutzgebiete' => [
        'label'    => 'Naturschutzgebiet',
        'paragraf' => '§ 21h Abs. 3 Nr. 6 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Der Betrieb über Naturschutzgebieten ist untersagt. Die gesetzliche Ausnahme setzt die Zustimmung der zuständigen Naturschutzbehörde voraus oder einen Betrieb oberhalb von 100 Metern, der nicht zu Freizeitzwecken erfolgt – für Hobbyflug greift sie damit nicht.',
    ],
    'nationalparks' => [
        'label'    => 'Nationalpark',
        'paragraf' => '§ 21h Abs. 3 Nr. 6 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Wie im Naturschutzgebiet: untersagt, solange keine Zustimmung der zuständigen Behörde vorliegt. Die Höhenausnahme gilt nur für nicht-freizeitlichen Betrieb.',
    ],
    'vogelschutzgebiete' => [
        'label'    => 'Europäisches Vogelschutzgebiet',
        'paragraf' => '§ 21h Abs. 3 Nr. 6 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Untersagt ohne Zustimmung der zuständigen Naturschutzbehörde. Gerade in Brut- und Rastzeiten wird sie in aller Regel nicht erteilt.',
    ],
    'ffh-gebiete' => [
        'label'    => 'FFH-Gebiet',
        'paragraf' => '§ 21h Abs. 3 Nr. 6 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Gebiet von gemeinschaftlicher Bedeutung nach der FFH-Richtlinie. Betrieb untersagt ohne Zustimmung der zuständigen Naturschutzbehörde.',
    ],

    // --- Luftverkehr ---
    'flughaefen' => [
        'label'    => 'Flughafen',
        'paragraf' => '§ 21h Abs. 3 Nr. 2 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 1.000 Metern um die Flughafenbegrenzung, zusätzlich verlängert entlang der An- und Abflugflächen. In der offenen Kategorie – also im gesamten Hobbybetrieb – nicht zulässig.',
    ],
    'flugplaetze' => [
        'label'    => 'Flugplatz',
        'paragraf' => '§ 21h Abs. 3 Nr. 1 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 1,5 Kilometern um die Flugplatzbegrenzung. Ein Betrieb ist nur mit Zustimmung der Luftaufsichtsstelle beziehungsweise des Flugplatzbetreibers zulässig.',
    ],
    'kontrollzonen' => [
        'label'    => 'Kontrollzone',
        'paragraf' => '§ 21h Abs. 3 Nr. 9 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'In Kontrollzonen ist der Betrieb nur zulässig, wenn eine Flugverkehrskontrollfreigabe nach § 21 LuftVO eingeholt wurde.',
    ],
    'flugbeschraenkungsgebiete' => [
        'label'    => 'Flugbeschränkungsgebiet',
        'paragraf' => '§ 21h Abs. 2 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Dauerhaft festgelegtes Flugbeschränkungsgebiet (ED-R). Der Betrieb ist nur unter den in der jeweiligen Festlegung genannten Bedingungen zulässig; diese sind bei der DFS abzurufen.',
    ],
    'haengegleiter' => [
        'label'    => 'Hängegleitergelände',
        'paragraf' => 'DIPUL-Hinweisgebiet',
        'ampel'    => 'gelb',
        'text'     => 'Start- und Landegelände für Hängegleiter und Gleitschirme. Kein eigenes Verbot, aber erhebliches Kollisionsrisiko mit bemanntem Luftsport in geringer Höhe.',
        'nur_karte' => true,   // im WFS nicht vorhanden – nur als WMS-Overlay verfügbar
    ],
    'modellflugplaetze' => [
        'label'    => 'Modellfluggelände',
        'paragraf' => 'Aufstiegserlaubnis nach § 21a LuftVO',
        'ampel'    => 'info',
        'text'     => 'Gelände mit behördlicher Aufstiegserlaubnis. Der Flugbetrieb setzt die Zustimmung des Betreibers voraus – liegt sie vor, gelten dort häufig Erleichterungen gegenüber der offenen Kategorie.',
        'nur_karte' => true,   // im WFS nicht vorhanden – nur als WMS-Overlay verfügbar
    ],
    'temporaere_betriebseinschraenkungen' => [
        'label'    => 'Temporäre Betriebseinschränkung',
        'paragraf' => '§ 21h Abs. 2 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Kurzfristig eingerichtete Sperrung, etwa für Großveranstaltungen oder Einsatzlagen. Diese Zonen ändern sich täglich und werden deshalb nur live auf der Karte angezeigt, nie gespeichert. Vor jedem Flug erneut prüfen.',
    ],

    // --- Siedlung und Behoerden ---
    'wohngrundstuecke' => [
        'label'    => 'Wohngrundstück',
        'paragraf' => '§ 21h Abs. 3 Nr. 7 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Der Betrieb über Wohngrundstücken setzt die ausdrückliche Zustimmung der in ihren Rechten betroffenen Eigentümer oder Nutzungsberechtigten voraus.',
        'klassen'  => [
            // Der haeufigste Irrtum in der FPV-Szene – deshalb hier ausdruecklich:
            'u250' => [
                'ampel' => 'rot',
                'text'  => 'Auch unter 250 Gramm. Die Gewichtsausnahme in § 21h Abs. 3 Nr. 7 verlangt zusätzlich, dass das Gerät keine optischen, akustischen oder Funksignale empfangen, übertragen oder aufzeichnen kann. Kamera und Videosender schließen das aus – für FPV bleibt es deshalb bei der Zustimmungspflicht.',
            ],
        ],
    ],
    'freibaeder' => [
        'label'    => 'Freibad oder Badestrand',
        'paragraf' => '§ 21h Abs. 3 Nr. 8 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Der Betrieb ist auf Zeiten außerhalb des Bade- und Betriebsbetriebs beschränkt.',
    ],
    'krankenhaeuser' => [
        'label'    => 'Krankenhaus',
        'paragraf' => '§ 21h Abs. 3 Nr. 10 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit Zustimmung der Einrichtung. Rettungshubschrauber starten und landen hier ohne Vorankündigung.',
    ],
    'justizvollzugsanstalten' => [
        'label'    => 'Justizvollzugsanstalt',
        'paragraf' => '§ 21h Abs. 3 Nr. 4 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit ausdrücklicher Zustimmung der zuständigen Stelle.',
    ],
    'behoerden' => [
        'label'    => 'Oberste Bundes- oder Landesbehörde',
        'paragraf' => '§ 21h Abs. 3 Nr. 4 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern um Verfassungsorgane und oberste Behörden. Betrieb nur mit ausdrücklicher Zustimmung.',
    ],
    'diplomatische_vertretungen' => [
        'label'    => 'Diplomatische Vertretung',
        'paragraf' => '§ 21h Abs. 3 Nr. 4 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern um Botschaften und Konsulate. Betrieb nur mit ausdrücklicher Zustimmung.',
    ],
    'internationale_organisationen' => [
        'label'    => 'Internationale Organisation',
        'paragraf' => '§ 21h Abs. 3 Nr. 4 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit ausdrücklicher Zustimmung.',
    ],
    'polizei' => [
        'label'    => 'Liegenschaft der Polizei',
        'paragraf' => '§ 21h Abs. 3 Nr. 4 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit ausdrücklicher Zustimmung der Behörde.',
    ],
    'sicherheitsbehoerden' => [
        'label'    => 'Sicherheitsbehörde',
        'paragraf' => '§ 21h Abs. 3 Nr. 4 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit ausdrücklicher Zustimmung der Behörde.',
    ],
    'labore' => [
        'label'    => 'Labor der Sicherheitsstufe 4',
        'paragraf' => '§ 21h Abs. 3 Nr. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern um Einrichtungen der biologischen Schutzstufe 4. Betrieb nur mit ausdrücklicher Zustimmung.',
    ],
    'militaerische_anlagen' => [
        'label'    => 'Militärische Anlage',
        'paragraf' => '§ 21h Abs. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Militärischer Schutzbereich. Betrieb nur mit Zustimmung der zuständigen militärischen Dienststelle.',
    ],

    // --- Infrastruktur ---
    'bundesautobahnen' => [
        'label'    => 'Bundesautobahn',
        'paragraf' => '§ 21h Abs. 3 Nr. 5 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Seitlicher Schutzbereich von 100 Metern. Ein Betrieb ist nur unter den in § 21h Abs. 3 Nr. 5 genannten Voraussetzungen zulässig.',
    ],
    'bundesstrassen' => [
        'label'    => 'Bundesstraße',
        'paragraf' => '§ 21h Abs. 3 Nr. 5 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Seitlicher Schutzbereich von 100 Metern. Ein Betrieb ist nur unter den in § 21h Abs. 3 Nr. 5 genannten Voraussetzungen zulässig.',
    ],
    'bahnanlagen' => [
        'label'    => 'Bahnanlage',
        'paragraf' => '§ 21h Abs. 3 Nr. 5 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Seitlicher Schutzbereich von 100 Metern um Schienenwege und Bahnanlagen. Ein Betrieb ist nur unter den in § 21h Abs. 3 Nr. 5 genannten Voraussetzungen zulässig.',
    ],
    'binnenwasserstrassen' => [
        'label'    => 'Binnenwasserstraße',
        'paragraf' => '§ 21h Abs. 3 Nr. 5 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Seitlicher Schutzbereich von 100 Metern. Für das Queren von Wasserstraßen sieht das Gesetz eine gesonderte Regelung vor.',
    ],
    'seewasserstrassen' => [
        'label'    => 'Seewasserstraße',
        'paragraf' => '§ 21h Abs. 3 Nr. 5 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Seitlicher Schutzbereich von 100 Metern. Für das Queren von Wasserstraßen sieht das Gesetz eine gesonderte Regelung vor.',
    ],
    'schifffahrtsanlagen' => [
        'label'    => 'Schifffahrtsanlage',
        'paragraf' => '§ 21h Abs. 3 Nr. 5 LuftVO',
        'ampel'    => 'gelb',
        'text'     => 'Seitlicher Schutzbereich von 100 Metern um Schleusen, Wehre und vergleichbare Anlagen.',
    ],
    'stromleitungen' => [
        'label'    => 'Stromleitung',
        'paragraf' => '§ 21h Abs. 3 Nr. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern um Anlagen der Energieversorgung. Betrieb nur mit ausdrücklicher Zustimmung des Betreibers. Freileitungen sind aus der FPV-Perspektive zudem kaum rechtzeitig zu erkennen.',
    ],
    'umspannwerke' => [
        'label'    => 'Umspannwerk',
        'paragraf' => '§ 21h Abs. 3 Nr. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit ausdrücklicher Zustimmung des Betreibers.',
    ],
    'kraftwerke' => [
        'label'    => 'Kraftwerk',
        'paragraf' => '§ 21h Abs. 3 Nr. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern. Betrieb nur mit ausdrücklicher Zustimmung des Betreibers.',
    ],
    'industrieanlagen' => [
        'label'    => 'Industrieanlage',
        'paragraf' => '§ 21h Abs. 3 Nr. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern um Industrieanlagen. Betrieb nur mit ausdrücklicher Zustimmung des Betreibers – das gilt ausdrücklich auch für stillgelegte Anlagen, die als Bando reizvoll erscheinen.',
    ],
    'windkraftanlagen' => [
        'label'    => 'Windkraftanlage',
        'paragraf' => '§ 21h Abs. 3 Nr. 3 LuftVO',
        'ampel'    => 'rot',
        'text'     => 'Schutzbereich von 100 Metern um Anlagen der Energieerzeugung. Betrieb nur mit ausdrücklicher Zustimmung des Betreibers. Zusätzlich erzeugen die Rotoren Turbulenzen weit über den Rotorkreis hinaus.',
    ],

    // --- Pseudo-Zone: keine DIPUL-Zone, sondern EU-Betriebskategorie ---
    //
    // Diese "Zone" steht in keiner Behoerdenkarte. Sie ist der eigentliche
    // Grund, warum die Betriebsklasse ueberhaupt abgefragt wird: in der
    // Unterkategorie A3 gilt ein Mindestabstand von 150 m zu Wohn-,
    // Gewerbe-, Industrie- und Erholungsgebieten. Wir werten das bewusst
    // NICHT automatisch aus, sondern zeichnen einen 150-m-Kreis und
    // ueberlassen die Beurteilung dem Piloten (OSM-Landuse waere
    // stellenweise luecken- haft und wuerde falsch entwarnen).
    'a3_abstand' => [
        'label'       => '150-Meter-Abstand (Kategorie A3)',
        'paragraf'    => 'UAS.OPEN.040, EU-Verordnung 2019/947',
        'ampel'       => 'gelb',
        'text'        => 'Ab 250 Gramm ohne C-Klassifizierung fliegst du in der Unterkategorie A3. Dort ist ein Abstand von mindestens 150 Metern zu Wohn-, Gewerbe-, Industrie- und Erholungsgebieten einzuhalten. Diese Regel steht in keiner amtlichen Zonenkarte – prüfe den eingezeichneten Kreis im Satellitenbild.',
        'nur_klassen' => ['a3'],
    ],
];

// -------------------------------------------------------------
// c) Pflichten-Checkliste je Betriebsklasse
//
// Diese Punkte haengen NICHT vom Standort ab, sondern allein vom
// Geraet. Sie stehen bewusst neben der Zonenliste: die haeufigsten
// Verstoesse im Hobbybetrieb sind fehlende Registrierung und
// fehlender Spotter, nicht der Flug in einer Sperrzone.
// -------------------------------------------------------------
const PILOT_CHECKLIST = [
    'u250' => [
        'Betreiber-Registrierung beim Luftfahrt-Bundesamt: Pflicht, sobald das Gerät eine Kamera oder einen anderen Sensor für personenbezogene Daten trägt – bei FPV also praktisch immer. Die e-ID gehört sichtbar an den Copter.',
        'Kompetenznachweis: unter 250 Gramm nicht erforderlich.',
        'Maximale Flughöhe 120 Meter über Grund.',
        'Flug mit FPV-Brille nur mit Spotter, der den Copter durchgehend in Sichtweite hält und den Luftraum beobachtet.',
        'Haftpflichtversicherung mit Drohneneinschluss: Pflicht nach § 43 LuftVG.',
    ],
    'c1c2' => [
        'Betreiber-Registrierung beim Luftfahrt-Bundesamt, e-ID sichtbar am Copter.',
        'EU-Kompetenznachweis A1/A3 (kleiner Drohnenführerschein) erforderlich. Für C2 nahe an unbeteiligten Personen zusätzlich das EU-Fernpilotenzeugnis A2.',
        'Maximale Flughöhe 120 Meter über Grund.',
        'Flug mit FPV-Brille nur mit Spotter.',
        'Haftpflichtversicherung mit Drohneneinschluss: Pflicht nach § 43 LuftVG.',
    ],
    'a3' => [
        'Betreiber-Registrierung beim Luftfahrt-Bundesamt, e-ID sichtbar am Copter.',
        'EU-Kompetenznachweis A1/A3 (kleiner Drohnenführerschein) erforderlich.',
        'Mindestens 150 Meter Abstand zu Wohn-, Gewerbe-, Industrie- und Erholungsgebieten.',
        'Keine unbeteiligten Personen im Flugbereich.',
        'Maximale Flughöhe 120 Meter über Grund.',
        'Flug mit FPV-Brille nur mit Spotter.',
        'Haftpflichtversicherung mit Drohneneinschluss: Pflicht nach § 43 LuftVG.',
    ],
];

// -------------------------------------------------------------
// Hilfsfunktionen fuer die Bewertung
// -------------------------------------------------------------

/**
 * Liefert die Bewertung einer Zone fuer eine Betriebsklasse.
 * Gibt null zurueck, wenn die Zone fuer diese Klasse nicht gilt
 * (z. B. der A3-Abstand fuer einen Copter unter 250 g).
 */
function zone_verdict(string $layer, string $class): ?array
{
    if (!isset(ZONE_RULES[$layer])) {
        return null;
    }
    $rule = ZONE_RULES[$layer];

    if (isset($rule['nur_klassen']) && !in_array($class, $rule['nur_klassen'], true)) {
        return null;
    }

    // Klassenspezifische Ueberschreibung gewinnt gegenueber dem Standardtext
    $override = $rule['klassen'][$class] ?? [];

    return [
        'layer'    => $layer,
        'label'    => $rule['label'],
        'paragraf' => $rule['paragraf'],
        'ampel'    => $override['ampel'] ?? $rule['ampel'],
        'text'     => $override['text']  ?? $rule['text'],
    ];
}

/** Rangfolge der Ampelstufen – hoeher = dringlicher, fuer Sortierung und Gesamturteil */
function zone_ampel_rank(string $ampel): int
{
    return ['info' => 1, 'gelb' => 2, 'rot' => 3][$ampel] ?? 0;
}

/**
 * Alle DIPUL-Layer, die beim Spot-Check abgefragt werden.
 *
 * Ausgenommen sind bewusst:
 *   - temporaere_betriebseinschraenkungen: aendern sich taeglich, ein
 *     gespeichertes Ergebnis waere schon am naechsten Tag falsch
 *   - a3_abstand: keine DIPUL-Zone, sondern eine Regel der EU-Verordnung
 */
function dipul_checkable_layers(): array
{
    $skip = ['temporaere_betriebseinschraenkungen', 'a3_abstand'];

    $layers = [];
    foreach (ZONE_RULES as $layer => $rule) {
        // 'nur_karte' markiert Layer, die der WMS liefert, der WFS aber nicht
        // (haengegleiter, modellflugplaetze). Eine Abfrage darauf endet immer
        // im ExceptionReport und wuerde den gesamten Check scheitern lassen.
        if (in_array($layer, $skip, true) || !empty($rule['nur_karte'])) {
            continue;
        }
        $layers[] = $layer;
    }
    return $layers;
}

/** Loest einen Buendelnamen in die kommagetrennte DIPUL-Layerliste auf */
function dipul_bundle_layers(string $bundle): ?string
{
    return isset(DIPUL_BUNDLES[$bundle]) ? implode(',', DIPUL_BUNDLES[$bundle]) : null;
}

// Radius um den Spot, in dem Zonen als betroffen gelten.
// 150 m ist bewusst gewaehlt: es deckt sich mit dem A3-Mindestabstand
// und meldet lieber eine knapp verfehlte Zone, als eine zu verschweigen.
const GEOZONE_CHECK_RADIUS_M = 150;

// -------------------------------------------------------------
// Geltungsbereich: DIPUL kennt ausschliesslich deutsche Geozonen
//
// Fuer einen Spot jenseits der Grenze antwortet der WFS voellig
// korrekt mit "nichts gefunden" – nur bedeutet das dort eben NICHT
// "keine Zone", sondern "keine Daten". Genau diese Verwechslung
// waere eine Fehlentwarnung, das einzige echte Haftungsrisiko
// dieses Features. Deshalb wird der Fall vorher abgefangen und in
// der Oberflaeche als eigener Zustand angezeigt.
//
// Grobe Bounding-Box mit Puffer. Sie grenzt die Nachbarlaender nicht
// sauber ab – das ist gewollt: ein grenznaher Spot soll lieber
// geprueft werden, als faelschlich als "keine Daten" zu gelten.
// -------------------------------------------------------------
const DIPUL_BBOX_DE = [
    'lat_min' => 47.2,
    'lat_max' => 55.1,
    'lon_min' => 5.8,
    'lon_max' => 15.1,
];

function point_in_dipul_area(float $lat, float $lon): bool
{
    return $lat >= DIPUL_BBOX_DE['lat_min'] && $lat <= DIPUL_BBOX_DE['lat_max']
        && $lon >= DIPUL_BBOX_DE['lon_min'] && $lon <= DIPUL_BBOX_DE['lon_max'];
}

/**
 * Fragt den DIPUL-WFS ab, welche Zonen einen Spot beruehren.
 *
 * Rueckgabe:
 *   array  Liste ['zone_type' => Layername, 'zone_name' => Bezeichnung]
 *          (auch leer = geprueft, nichts gefunden)
 *   null   Abfrage fehlgeschlagen – der Aufrufer darf daraus KEINE
 *          Entwarnung ableiten, sondern muss "nicht geprueft" anzeigen
 *
 * Der WFS lehnt BBOX-Abfragen ueber mehrere typeNames ab ("Extracted
 * invalid join sub-filter"), deshalb ein Request pro Layer – parallel
 * ueber curl_multi, sonst waeren es rund 30 Roundtrips nacheinander.
 *
 * Die BBOX wird in CRS84 (urn:ogc:def:crs:OGC:1.3:CRS84) uebergeben.
 * Das ist eindeutig Laenge/Breite; die Kurzform "EPSG:4326" haette je
 * nach Serverauslegung die umgekehrte Achsenreihenfolge bedeutet.
 */
function fetchZonesForPoint(float $lat, float $lon): ?array
{
    $layers = dipul_checkable_layers();

    // Meter -> Grad: Breite konstant, Laenge mit dem Breitengrad gestaucht
    $dLat = GEOZONE_CHECK_RADIUS_M / 111320.0;
    $cos  = max(cos(deg2rad($lat)), 0.01);          // Division durch ~0 an den Polen vermeiden
    $dLon = GEOZONE_CHECK_RADIUS_M / (111320.0 * $cos);

    $bbox = sprintf(
        '%.7F,%.7F,%.7F,%.7F,urn:ogc:def:crs:OGC:1.3:CRS84',
        $lon - $dLon, $lat - $dLat, $lon + $dLon, $lat + $dLat
    );

    $multi   = curl_multi_init();
    $handles = [];

    foreach ($layers as $layer) {
        $url = DIPUL_WFS_URL . '?' . http_build_query([
            'service'      => 'WFS',
            'version'      => '2.0.0',
            'request'      => 'GetFeature',
            'typeNames'    => $layer,
            'outputFormat' => 'application/json',
            'count'        => 20,
            'bbox'         => $bbox,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'fpv-spots-germany.de Zonencheck',
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$layer] = $ch;
    }

    // Alle Requests parallel abarbeiten
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $zones  = [];
    $failed = [];

    foreach ($handles as $layer => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);

        if ($code !== 200 || !is_string($body) || $body === '') {
            $failed[] = $layer;
            continue;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
            // Bei einem Fehler antwortet der WFS mit einem XML-ExceptionReport
            $failed[] = $layer;
            continue;
        }

        foreach ($data['features'] as $feature) {
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $name  = (string) ($props['name'] ?? $props['generated_name_DE'] ?? '');
            $zones[] = [
                'zone_type' => $layer,
                'zone_name' => mb_substr(trim($name), 0, 255),
            ];
        }
    }

    curl_multi_close($multi);

    // Teilergebnisse werden bewusst verworfen: eine unvollstaendige
    // Zonenliste sieht aus wie eine Entwarnung, ist aber keine.
    if ($failed !== []) {
        error_log('geozones: WFS-Abfrage fehlgeschlagen fuer ' . implode(', ', $failed));
        return null;
    }

    // Duplikate zusammenfassen (mehrere Teilflaechen derselben Zone)
    $unique = [];
    foreach ($zones as $z) {
        $unique[$z['zone_type'] . '|' . $z['zone_name']] = $z;
    }

    return array_values($unique);
}

// =============================================================
// Persistenz des Spot-Checks
//
// Getrennt von fetchZonesForPoint(), damit der Katalog selbst ohne
// Datenbank nutzbar bleibt (der WMS-Proxy braucht kein PDO).
// =============================================================

/**
 * Prueft einen Spot gegen die DIPUL-Zonen und schreibt das Ergebnis.
 *
 * Wichtig: schlaegt die Abfrage fehl, bleibt zones_checked_at unveraendert
 * (bei neuen Spots also NULL). Nur so laesst sich "keine Zone gefunden" von
 * "nie geprueft" unterscheiden – wuerden wir den Zeitstempel trotzdem setzen,
 * erschiene ein fehlgeschlagener Check als Entwarnung.
 *
 * @return bool true, wenn geprueft und gespeichert wurde
 */
function refresh_spot_zones(PDO $pdo, int $spotId, float $lat, float $lon): bool
{
    // Ausserhalb Deutschlands gibt es nichts abzufragen. Der Spot wird
    // trotzdem als geprueft markiert, damit der Cron ihn nicht endlos
    // erneut anfasst; die Detailseite erkennt den Fall an den Koordinaten
    // und zeigt "ausserhalb des DIPUL-Gebiets" statt "keine Zone".
    $zones = point_in_dipul_area($lat, $lon) ? fetchZonesForPoint($lat, $lon) : [];
    if ($zones === null) {
        return false;   // Cron holt es spaeter nach
    }

    try {
        $pdo->beginTransaction();

        // Vollstaendig ersetzen: Zonen koennen auch wegfallen
        $pdo->prepare('DELETE FROM spot_zones WHERE spot_id = ?')->execute([$spotId]);

        if ($zones !== []) {
            // INSERT IGNORE: die Namenskuerzung auf 255 Zeichen kann Duplikate erzeugen
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO spot_zones (spot_id, zone_type, zone_name) VALUES (?, ?, ?)'
            );
            foreach ($zones as $z) {
                $ins->execute([$spotId, $z['zone_type'], $z['zone_name']]);
            }
        }

        $pdo->prepare('UPDATE spots SET zones_checked_at = NOW() WHERE id = ?')->execute([$spotId]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('geozones: Speichern der Zonen fehlgeschlagen: ' . $e->getMessage());
        return false;
    }

    return true;
}

/**
 * Gespeicherte Zonen eines Spots laden, strengste Ampelstufe zuerst.
 *
 * Layer ohne Eintrag in ZONE_RULES werden uebersprungen: lieber eine Zone
 * nicht anzeigen, als sie ohne Rechtsgrundlage und Erklaerung zu zeigen.
 *
 * Rueckgabe:
 *   array  Liste der Zonen (auch leer = geprueft, nichts gefunden)
 *   null   Zonen konnten nicht gelesen werden (z. B. fehlende Tabelle)
 *
 * Die Unterscheidung ist Absicht: ein leeres Array laesst die Detailseite
 * "keine Zone gefunden" anzeigen. Waere das auch die Antwort auf einen
 * Datenbankfehler, wuerde ein fehlgeschlagener Lesevorgang wie eine
 * Freigabe aussehen – die Fehlentwarnung, die dieses Feature um jeden
 * Preis vermeiden soll (siehe Kopfkommentar). Der Aufrufer behandelt
 * null deshalb wie "nie geprueft".
 */
function load_spot_zones(PDO $pdo, int $spotId, string $class): ?array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT zone_type, zone_name FROM spot_zones WHERE spot_id = ? ORDER BY zone_type, zone_name'
        );
        $stmt->execute([$spotId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('geozones: Laden der Zonen fehlgeschlagen: ' . $e->getMessage());
        return null;
    }

    $result = [];
    foreach ($rows as $row) {
        $verdict = zone_verdict((string) $row['zone_type'], $class);
        if ($verdict === null) {
            continue;
        }
        $verdict['zone_name'] = (string) $row['zone_name'];
        $verdict['layer']     = (string) $row['zone_type'];
        $result[] = $verdict;
    }

    usort($result, static function (array $a, array $b): int {
        $rank = zone_ampel_rank($b['ampel']) <=> zone_ampel_rank($a['ampel']);
        return $rank !== 0 ? $rank : strcmp($a['label'], $b['label']);
    });

    return $result;
}
