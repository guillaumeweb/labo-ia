<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';

header('Content-Type: application/json');

$entree = json_decode(file_get_contents('php://input'), true);
$messages = $entree['messages'] ?? [];
$modele = $entree['model'] ?? '';

if (empty($messages)) {
    claude_erreur_json('Aucun message reçu.', 400);
}

$reponse = claude_appeler($messages, [
    'model' => $modele,
    'system' => "Tu es un assistant pédagogique dans un labo d'apprentissage de l'IA. Réponds de façon claire et concise, en français.",
]);

echo json_encode([
    'reponse' => claude_extraire_texte($reponse),
    'brut'    => $reponse,
], JSON_UNESCAPED_UNICODE);
