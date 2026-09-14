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
$maxAllersRetours = 6;

for ($tour = 1; $tour <= $maxAllersRetours; $tour++) {
    $reponse = claude_appeler($messages, [
        'tools'  => outils_definitions(),
        'system' => "Tu es un agent pédagogique qui peut enchaîner plusieurs outils (météo, calcul) avant de répondre. " .
                    "N'hésite pas à appeler un outil plusieurs fois si la question le demande (ex: comparer deux villes). " .
                    "Réponds en français, et ne donne ta réponse finale que quand tu as toutes les informations nécessaires.",
    ]);

    $messages[] = ['role' => 'assistant', 'content' => $reponse['content']];

    if (($reponse['stop_reason'] ?? '') !== 'tool_use') {
        $texteFinal = claude_extraire_texte($reponse);
        $etapes[] = [
            'type'   => "étape $tour — réponse finale",
            'detail' => 'Claude estime avoir assez d\'informations et rédige sa réponse.',
        ];
        break;
    }

    $resultatsOutils = [];
    foreach ($reponse['content'] as $bloc) {
        if (($bloc['type'] ?? '') !== 'tool_use') {
            continue;
        }
        $resultat = outils_executer($bloc['name'], $bloc['input'] ?? []);
        $etapes[] = [
            'type'   => "étape $tour — appel outil",
            'detail' => htmlspecialchars($bloc['name']) . '(' . htmlspecialchars(json_encode($bloc['input'], JSON_UNESCAPED_UNICODE)) . ')' .
                        ' → ' . htmlspecialchars($resultat),
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
    'reponse'    => $texteFinal ?: "(L'agent n'a pas conclu après $maxAllersRetours étapes — c'est aussi une leçon : sans limite, un agent peut boucler indéfiniment.)",
    'etapes'     => $etapes,
    'historique' => $messages,
], JSON_UNESCAPED_UNICODE);
