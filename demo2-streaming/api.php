<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // évite la bufferisation sur certains serveurs (ex: nginx)
set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_clean();
}

$entree = json_decode(file_get_contents('php://input'), true);
$messages = $entree['messages'] ?? [];
$modele = $entree['model'] ?? '';

if (empty($messages)) {
    echo "data: " . json_encode(['texte' => '[Erreur : aucun message reçu]']) . "\n\n";
    flush();
    exit;
}

claude_appeler_stream(
    $messages,
    [
        'model' => $modele,
        'system' => "Tu es un assistant pédagogique dans un labo d'apprentissage de l'IA. Réponds de façon claire et concise, en français.",
    ],
    function (string $fragment) {
        echo "data: " . json_encode(['texte' => $fragment], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
);
