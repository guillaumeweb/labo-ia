<?php
/**
 * Définition et exécution des "outils" (function calling) utilisés par les
 * démos 4 et 5. Chaque outil est une simple fonction PHP ; Claude ne
 * l'exécute jamais lui-même, il ne fait que demander qu'on l'exécute et on
 * lui renvoie le résultat texte.
 */

if (!defined('LABO_IA_APP')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function outils_definitions(): array {
    return [
        [
            'name' => 'meteo_actuelle',
            'description' => 'Donne la météo actuelle (température, conditions) pour une ville donnée dans le monde.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'ville' => ['type' => 'string', 'description' => 'Nom de la ville, ex: Lyon, Paris, Tokyo'],
                ],
                'required' => ['ville'],
            ],
        ],
        [
            'name' => 'calculer',
            'description' => "Évalue une expression arithmétique simple (ex: '12 * (3 + 4)').",
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'expression' => ['type' => 'string', 'description' => 'Expression mathématique à calculer'],
                ],
                'required' => ['expression'],
            ],
        ],
    ];
}

function outil_meteo(string $ville): string {
    $url = 'https://wttr.in/' . rawurlencode($ville) . '?format=3';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'curl', // wttr.in adapte sa réponse selon le user-agent
    ]);
    $resultat = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resultat === false || $code >= 400) {
        return "Erreur : impossible de récupérer la météo pour \"$ville\".";
    }
    return trim($resultat);
}

function outil_calculer(string $expression): string {
    // On n'autorise que chiffres, espaces et opérateurs de base : jamais d'eval() sur une expression libre.
    if (!preg_match('/^[0-9\s.,+\-*\/()]+$/', $expression)) {
        return "Erreur : expression non autorisée (chiffres et opérateurs +-*/() uniquement).";
    }
    try {
        // On passe par un mini-parseur sûr plutôt qu'eval().
        $resultat = outil_calculer_evaluer($expression);
        return (string) $resultat;
    } catch (Throwable $e) {
        return "Erreur de calcul : " . $e->getMessage();
    }
}

/** Évaluateur d'expression arithmétique minimal (sans eval), pour rester sûr même si le regex ci-dessus était contourné. */
function outil_calculer_evaluer(string $expr) {
    $expr = str_replace(',', '.', $expr);
    $jetons = preg_split('/\s*([+\-*\/()])\s*/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $position = 0;

    $lireNombre = function () use (&$jetons, &$position) {
        if (!isset($jetons[$position]) || !is_numeric($jetons[$position])) {
            throw new Exception("expression invalide");
        }
        return (float) $jetons[$position++];
    };
    $lireFacteur = function () use (&$jetons, &$position, &$lireFacteur, &$lireNombre, &$lireExpression) {
        if (($jetons[$position] ?? null) === '(') {
            $position++;
            $valeur = $lireExpression();
            if (($jetons[$position] ?? null) !== ')') throw new Exception("parenthèse manquante");
            $position++;
            return $valeur;
        }
        return $lireNombre();
    };
    $lireTerme = function () use (&$jetons, &$position, &$lireFacteur) {
        $valeur = $lireFacteur();
        while (in_array($jetons[$position] ?? null, ['*', '/'], true)) {
            $op = $jetons[$position++];
            $droite = $lireFacteur();
            $valeur = $op === '*' ? $valeur * $droite : $valeur / $droite;
        }
        return $valeur;
    };
    $lireExpression = function () use (&$jetons, &$position, &$lireTerme) {
        $valeur = $lireTerme();
        while (in_array($jetons[$position] ?? null, ['+', '-'], true)) {
            $op = $jetons[$position++];
            $droite = $lireTerme();
            $valeur = $op === '+' ? $valeur + $droite : $valeur - $droite;
        }
        return $valeur;
    };

    $resultat = $lireExpression();
    if ($position !== count($jetons)) throw new Exception("expression invalide");
    return $resultat;
}

/** Exécute un outil par son nom (venant d'un bloc tool_use de Claude) et renvoie le résultat texte. */
function outils_executer(string $nom, array $entree): string {
    return match ($nom) {
        'meteo_actuelle' => outil_meteo($entree['ville'] ?? ''),
        'calculer'       => outil_calculer($entree['expression'] ?? ''),
        default          => "Erreur : outil \"$nom\" inconnu.",
    };
}
