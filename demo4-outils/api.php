<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/outils.php';

header('Content-Type: application/json');

$entree = json_decode(file_get_contents('php://input'), true);
$messages = $entree['messages'] ?? [];

if (empty($messages)) {
    claude_erreur_json('Aucun message reçu.', 400);
}

$etapes = [];
$texteFinal = '';
$maxAllersRetours = 4;

for ($tour = 0; $tour < $maxAllersRetours; $tour++) {
    $reponse = claude_appeler($messages, [
        'tools'  => outils_definitions(),
        'system' => "Tu es un assistant pédagogique qui peut utiliser des outils (météo, calcul). Réponds en français.",
    ]);

    // On ajoute la réponse de Claude (telle quelle, avec ses éventuels blocs tool_use) à l'historique.
    $messages[] = ['role' => 'assistant', 'content' => $reponse['content']];

    if (($reponse['stop_reason'] ?? '') !== 'tool_use') {
        $texteFinal = claude_extraire_texte($reponse);
        break;
    }

    // Claude a demandé un ou plusieurs outils : on les exécute et on prépare les résultats.
    $resultatsOutils = [];
    foreach ($reponse['content'] as $bloc) {
        if (($bloc['type'] ?? '') !== 'tool_use') {
            continue;
        }
        $resultat = outils_executer($bloc['name'], $bloc['input'] ?? []);
        $etapes[] = [
            'type'   => 'appel outil',
            'detail' => htmlspecialchars($bloc['name']) . '(' . htmlspecialchars(json_encode($bloc['input'], JSON_UNESCAPED_UNICODE)) . ')',
        ];
        $etapes[] = [
            'type'   => 'résultat',
            'detail' => htmlspecialchars($resultat),
        ];
        $resultatsOutils[] = [
            'type' => 'tool_result',
            'tool_use_id' => $bloc['id'],
            'content' => $resultat,
        ];
    }
    $messages[] = ['role' => 'user', 'content' => $resultatsOutils];
}

echo json_encode([
    'reponse'    => $texteFinal ?: "(Claude n'a pas terminé après $maxAllersRetours étapes.)",
    'etapes'     => $etapes,
    'historique' => $messages,
], JSON_UNESCAPED_UNICODE);
