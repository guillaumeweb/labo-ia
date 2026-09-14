# Labo IA

Projet perso pour comprendre concrètement différentes architectures IA, en les
construisant plutôt qu'en les lisant. Cinq démos, hébergées sur un sous-domaine
OVH (`labo-ia.lamaisonauxvoletsblancs.com`), avec le même mode de
fonctionnement que le site du gîte : sites statiques + PHP, déploiement
automatique via Git.

## Les 5 démos

1. **`demo1-chat/`** — appel simple à l'API Messages de Claude (question → réponse complète).
2. **`demo2-streaming/`** — même chose, mais en streaming (Server-Sent Events) : la réponse s'affiche mot par mot.
3. **`demo3-rag/`** — RAG simplifié : découpe un document en paragraphes, sélectionne les plus pertinents par recherche de mots-clés (pas de vrais embeddings, pour rester lisible), et les injecte dans le prompt.
4. **`demo4-outils/`** — function calling : Claude peut demander l'exécution d'un outil (météo via wttr.in, ou un calcul) plutôt que de répondre directement en texte.
5. **`demo5-agent/`** — la même mécanique que la démo 4, mais en boucle : Claude peut enchaîner plusieurs appels d'outils avant de conclure. C'est le principe de base d'un agent (comme Claude Code).

## Architecture technique

- **`lib/claude.php`** — client HTTP minimal (cURL) vers `api.anthropic.com/v1/messages`, en mode classique et streaming.
- **`lib/outils.php`** — définition et exécution des "outils" utilisés par les démos 4 et 5.
- **`includes/`** — en-tête / pied de page HTML partagés.
- Chaque démo est un dossier avec `index.php` (interface) et `api.php` (point d'entrée appelé en JS depuis le navigateur).

## Mise en route

1. Copier `claude-config.example.php` vers `claude-config.php` et renseigner ta clé API (créée sur [console.anthropic.com](https://console.anthropic.com), Settings → API Keys).
2. `claude-config.php` est dans `.gitignore` : il ne part jamais sur Git. Sur le serveur OVH, il doit être déposé **manuellement par FTP**, comme `brevo-config.php` sur le site du gîte.
3. Vérifier que le sous-domaine OVH est configuré en PHP 8.1 ou plus récent (nécessaire pour `match()`, `str_starts_with()`, etc. utilisés dans le code).

## Coût

Ce projet appelle l'API Claude en facturation à l'usage (pas inclus dans un
abonnement Claude / Claude Code). Pour un usage perso ponctuel (quelques
dizaines de messages), le coût reste de l'ordre de quelques centimes — mais ce
n'est pas gratuit. Modèle par défaut : Haiku 4.5 (le moins cher des deux
proposés dans les démos).

## Sécurité

- La clé API n'est jamais exposée côté navigateur : tout passe par `api.php` côté serveur.
- L'outil "calculer" n'utilise jamais `eval()` : un petit évaluateur d'expression maison s'en charge, pour ne jamais exécuter de code arbitraire.
- `lib/` est bloqué à l'accès web direct via `.htaccess` (defense in depth — ces fichiers ne contiennent pas de logique déclenchable seuls, mais autant fermer la porte).
