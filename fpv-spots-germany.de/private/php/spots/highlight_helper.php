<?php
declare(strict_types=1);
// =============================================================
// Highlight-Bilder eines Spots
// -------------------------------------------------------------
// Spot-Ersteller und Admins legen bis zu drei Fotos als Highlights
// fest (spot_images.highlight_pos = 1..3, NULL = kein Highlight).
// Platz 1 ist das Titelbild: groß im Mosaik, auf dem Handy allein
// sichtbar, Cover im Dashboard und erstes Bild im Karten-Popup.
// Freie Plätze werden mit den neuesten Fotos aufgefüllt.
// =============================================================

// SQL-Gegenstück (Karten-Popup, Dashboard-Cover):
//   ORDER BY highlight_pos IS NULL, highlight_pos, created_at DESC, id DESC
const SPOT_HIGHLIGHTS_MAX = 3;

// =============================================================
// Mosaik-Auswahl aus einer bereits geladenen Bildliste
//
// $images: Zeilen mit 'highlight_pos', sortiert nach "neueste zuerst".
// Ergebnis: Highlights nach Platz, aufgefüllt mit den neuesten
// Nicht-Highlights, höchstens $n Einträge.
// =============================================================
function spot_hero_images(array $images, int $n = SPOT_HIGHLIGHTS_MAX): array
{
    $highlights = [];
    $rest       = [];
    foreach ($images as $img) {
        $pos = (int)($img['highlight_pos'] ?? 0);
        if ($pos >= 1 && $pos <= SPOT_HIGHLIGHTS_MAX) {
            $highlights[] = $img;
        } else {
            $rest[] = $img;
        }
    }

    // Stabil nach Platz sortieren (Lücken wie "nur 2 und 3" rücken auf)
    usort($highlights, static fn(array $a, array $b): int
        => (int)$a['highlight_pos'] <=> (int)$b['highlight_pos']);

    return array_slice(array_merge($highlights, $rest), 0, $n);
}

// =============================================================
// Highlights eines Spots neu setzen
//
// $imageIds in gewünschter Reihenfolge (Index 0 = Platz 1). Eine
// leere Liste setzt alle Highlights zurück (Automatik). Fremde
// Bild-IDs greifen nicht, weil jedes UPDATE auf spot_id prüft.
// Die Berechtigung prüft der Aufrufer.
// =============================================================
function spot_set_highlights(PDO $pdo, int $spotId, array $imageIds): void
{
    $imageIds = array_slice(array_values(array_unique(array_map('intval', $imageIds))), 0, SPOT_HIGHLIGHTS_MAX);
    $imageIds = array_values(array_filter($imageIds, static fn(int $id): bool => $id > 0));

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // Erst alle Plätze leeren, sonst kollidiert der UNIQUE-Key (spot_id, highlight_pos)
        $pdo->prepare('UPDATE spot_images SET highlight_pos = NULL WHERE spot_id = ?')
            ->execute([$spotId]);

        $set = $pdo->prepare('UPDATE spot_images SET highlight_pos = ? WHERE id = ? AND spot_id = ?');
        foreach ($imageIds as $i => $imageId) {
            $set->execute([$i + 1, $imageId, $spotId]);
        }

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
