<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';

header('Content-Type: application/json');

$entree = json_decode(file_get_contents('php://input'), true);
$document = trim($entree['document'] ?? '');
$question = trim($entree['question'] ?? '');

if ($document === '' || $question === '') {
    claude_erreur_json('Document ou question manquant.', 400);
}

/** Découpe naïve par paragraphe (ligne vide). */
function rag_decouper(string $texte): array {
    $morceaux = preg_split('/\n\s*\n/', $texte);
    return array_values(array_filter(array_map('trim', $morceaux), fn($m) => $m !== ''));
}

/** Score de pertinence très simple : nombre de mots (>=4 lettres) en commun entre la question et le morceau. */
function rag_mots_significatifs(string $texte): array {
    $texte = mb_strtolower($texte);
    $texte = preg_replace('/[^\pL\s]/u', ' ', $texte); // enlève ponctuation
    $mots = preg_split('/\s+/', $texte, -1, PREG_SPLIT_NO_EMPTY);
    return array_filter($mots, fn($m) => mb_strlen($m) >= 4);
}

function rag_score(array $motsQuestion, string $chunk): int {
    $motsChunk = rag_mots_significatifs($chunk);
    $communs = array_intersect($motsQuestion, $motsChunk);
    return count($communs);
}

$chunks = rag_decouper($document);
$motsQuestion = rag_mots_significatifs($question);

$scores = array_map(fn($c) => rag_score($motsQuestion, $c), $chunks);

// Trie les index par score décroissant, garde les 3 meilleurs (score > 0 si possible).
$indices = array_keys($chunks);
usort($indices, fn($a, $b) => $scores[$b] <=> $scores[$a]);
$indicesRetenus = array_slice($indices, 0, min(3, count($indices)));

$chunksPourReponse = [];
$extraitsPourClaude = [];
foreach ($chunks as $i => $texte) {
    $retenu = in_array($i, $indicesRetenus, true);
    $chunksPourReponse[] = ['texte' => $texte, 'score' => $scores[$i], 'retenu' => $retenu];
    if ($retenu) {
        $extraitsPourClaude[] = $texte;
    }
}

$contexte = implode("\n\n---\n\n", $extraitsPourClaude);

$reponse = claude_appeler([
    ['role' => 'user', 'content' => "Voici des extraits d'un document :\n\n$contexte\n\n---\n\nEn te basant uniquement sur ces extraits, réponds à cette question : $question\n\nSi la réponse ne se trouve pas dans les extraits, dis-le clairement."],
], [
    'system' => "Tu es un assistant qui répond uniquement à partir des extraits fournis, sans utiliser de connaissances externes. Réponds en français, de façon concise.",
]);

echo json_encode([
    'chunks'   => $chunksPourReponse,
    'reponse'  => claude_extraire_texte($reponse),
], JSON_UNESCAPED_UNICODE);
