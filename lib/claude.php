<?php
/**
 * Petit client PHP pour l'API Messages d'Anthropic (Claude).
 * Aucune dépendance externe : juste cURL, présent sur l'hébergement OVH.
 *
 * Ce fichier ne doit jamais être appelé directement depuis le navigateur :
 * chaque démo doit definir LABO_IA_APP avant de l'inclure.
 */

if (!defined('LABO_IA_APP')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
const CLAUDE_API_VERSION = '2023-06-01';

/** Modèles autorisés dans les démos (liste blanche : jamais accepter un nom de modèle venu du client sans le valider contre cette liste). */
function claude_modeles_disponibles(): array {
    return [
        'claude-haiku-4-5-20251001' => 'Haiku 4.5 — rapide et économique',
        'claude-sonnet-5'           => 'Sonnet 5 — plus capable',
    ];
}

function claude_modele_valide(string $modele): string {
    $dispo = claude_modeles_disponibles();
    return array_key_exists($modele, $dispo) ? $modele : array_key_first($dispo);
}

/** Charge la config locale (clé API). Renvoie une erreur JSON claire si le fichier n'existe pas encore. */
function claude_charger_config(): array {
    $chemin = __DIR__ . '/../claude-config.php';
    if (!file_exists($chemin)) {
        claude_erreur_json(
            "claude-config.php est introuvable. Copie claude-config.example.php vers " .
            "claude-config.php et renseigne ta clé API Anthropic (voir console.anthropic.com).",
            500
        );
    }
    $config = require $chemin;
    if (empty($config['api_key']) || $config['api_key'] === 'sk-ant-REMPLACE-MOI') {
        claude_erreur_json("La clé API n'est pas renseignée dans claude-config.php.", 500);
    }
    return $config;
}

function claude_erreur_json(string $message, int $code = 500): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['erreur' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Appelle l'API Messages en mode classique (réponse complète en une fois).
 *
 * @param array $messages Tableau de messages format Anthropic ([['role'=>'user','content'=>'...'], ...])
 * @param array $options  Clés optionnelles : model, system, max_tokens, tools
 * @return array La réponse décodée de l'API (voir docs.anthropic.com)
 */
function claude_appeler(array $messages, array $options = []): array {
    $config = claude_charger_config();

    $corps = [
        'model'      => claude_modele_valide($options['model'] ?? array_key_first(claude_modeles_disponibles())),
        'max_tokens' => $options['max_tokens'] ?? 1024,
        'messages'   => $messages,
    ];
    if (!empty($options['system'])) {
        $corps['system'] = $options['system'];
    }
    if (!empty($options['tools'])) {
        $corps['tools'] = $options['tools'];
    }

    $ch = curl_init(CLAUDE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($corps, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $config['api_key'],
            'anthropic-version: ' . CLAUDE_API_VERSION,
        ],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $reponseBrute = curl_exec($ch);
    $erreurCurl = curl_error($ch);
    $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($reponseBrute === false) {
        claude_erreur_json("Erreur réseau vers l'API Claude : $erreurCurl");
    }
    $reponse = json_decode($reponseBrute, true);
    if ($codeHttp >= 400) {
        $msg = $reponse['error']['message'] ?? $reponseBrute;
        claude_erreur_json("L'API Claude a répondu une erreur ($codeHttp) : $msg", 502);
    }
    return $reponse;
}

/**
 * Extrait le texte simple d'une réponse Claude (concatène les blocs de type "text").
 */
function claude_extraire_texte(array $reponse): string {
    $texte = '';
    foreach ($reponse['content'] ?? [] as $bloc) {
        if (($bloc['type'] ?? '') === 'text') {
            $texte .= $bloc['text'];
        }
    }
    return $texte;
}

/**
 * Appelle l'API Messages en mode streaming (Server-Sent Events) et transmet
 * chaque fragment de texte au navigateur au fur et à mesure, via une closure.
 *
 * @param callable $surFragment function(string $texteFragment): void
 */
function claude_appeler_stream(array $messages, array $options, callable $surFragment): void {
    $config = claude_charger_config();

    $corps = [
        'model'      => claude_modele_valide($options['model'] ?? array_key_first(claude_modeles_disponibles())),
        'max_tokens' => $options['max_tokens'] ?? 1024,
        'messages'   => $messages,
        'stream'     => true,
    ];
    if (!empty($options['system'])) {
        $corps['system'] = $options['system'];
    }

    $tamponLigne = '';

    $ch = curl_init(CLAUDE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode($corps, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . $config['api_key'],
            'anthropic-version: ' . CLAUDE_API_VERSION,
            'accept: text/event-stream',
        ],
        CURLOPT_TIMEOUT       => 120,
        CURLOPT_WRITEFUNCTION => function ($curlHandle, $donnees) use (&$tamponLigne, $surFragment) {
            $tamponLigne .= $donnees;
            // Les événements SSE sont séparés par une ligne vide.
            while (($pos = strpos($tamponLigne, "\n\n")) !== false) {
                $evenement = substr($tamponLigne, 0, $pos);
                $tamponLigne = substr($tamponLigne, $pos + 2);
                foreach (explode("\n", $evenement) as $ligne) {
                    if (str_starts_with($ligne, 'data: ')) {
                        $donneesJson = json_decode(substr($ligne, 6), true);
                        if (($donneesJson['type'] ?? '') === 'content_block_delta') {
                            $texte = $donneesJson['delta']['text'] ?? '';
                            if ($texte !== '') {
                                $surFragment($texte);
                            }
                        }
                    }
                }
            }
            return strlen($donnees);
        },
    ]);
    $ok = curl_exec($ch);
    if ($ok === false) {
        $surFragment("\n[Erreur réseau vers l'API Claude : " . curl_error($ch) . "]");
    }
    curl_close($ch);
}
