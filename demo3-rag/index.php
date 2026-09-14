<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';
$titre = 'Demo 3 — RAG';

$documentExemple = <<<TEXTE
Le thé provient à l'origine de Chine, où sa consommation remonterait à plusieurs millénaires. La légende attribue sa découverte à l'empereur Shennong, vers 2737 avant notre ère : des feuilles de théier seraient tombées par hasard dans son eau chaude.

Il existe cinq grandes familles de thé, qui dépendent surtout du degré d'oxydation des feuilles : le thé vert (non oxydé), le thé blanc (à peine oxydé), le thé oolong (partiellement oxydé), le thé noir (totalement oxydé) et le thé pu-erh (fermenté). Toutes proviennent pourtant de la même plante, Camellia sinensis.

Au Japon, la cérémonie du thé (chanoyu) est un art codifié qui met l'accent sur la simplicité, le respect et la sérénité du geste. Elle utilise traditionnellement du matcha, un thé vert réduit en poudre fine.

Sur le plan de la santé, le thé contient des antioxydants appelés polyphénols, ainsi que de la théine (chimiquement identique à la caféine), en quantité généralement plus faible que dans le café.

Aujourd'hui, les plus gros producteurs mondiaux de thé sont la Chine et l'Inde, suivies du Kenya et du Sri Lanka. Le Royaume-Uni, lui, reste l'un des plus gros consommateurs par habitant en Europe, un héritage de son passé colonial en Asie.
TEXTE;

require __DIR__ . '/../includes/entete.php';
?>

<main>
  <div class="conteneur">
    <div class="entete-page">
      <span class="etiquette">Démo 3 / 5</span>
      <h1>RAG — augmenter le contexte</h1>
      <p>Claude n'a jamais "appris" ton document personnel. Le RAG (<em>Retrieval-Augmented Generation</em>) consiste à retrouver, dans un texte, les passages pertinents pour la question posée, puis à les glisser dans le message envoyé à l'IA — qui répond alors comme si elle "connaissait" ce document.</p>
    </div>

    <div class="explication">
      <strong>Comment ça marche (version simplifiée, pédagogique) :</strong>
      <ol>
        <li>Le document est découpé en petits morceaux ("chunks"), ici par paragraphe.</li>
        <li>Chaque morceau reçoit un score de pertinence par rapport à ta question (ici : simple comptage de mots en commun — une vraie application utiliserait des "embeddings", une représentation mathématique du sens des phrases).</li>
        <li>Seuls les 2-3 morceaux les plus pertinents sont envoyés à Claude, avec la question.</li>
        <li>Claude répond en se basant uniquement sur ces extraits — pas sur le document entier.</li>
      </ol>
      Note : cette démo utilise une recherche par mots-clés pour rester simple. En production, on utiliserait des <em>embeddings</em> (via une API comme Voyage AI) pour retrouver les passages pertinents même sans mots en commun.
    </div>

    <label style="color:var(--texte-doux); font-size:0.85rem;">Document (modifiable) :</label>
    <textarea id="document" class="textarea-doc" style="margin:8px 0 16px;"><?= htmlspecialchars($documentExemple) ?></textarea>

    <div class="chat-saisie" style="border:1px solid var(--bordure); border-radius:var(--rayon); background:var(--fond-carte); margin-bottom:16px;">
      <textarea id="question" rows="2" placeholder="Pose une question sur le document…"></textarea>
      <button class="bouton" id="interroger">Interroger</button>
    </div>

    <div id="resultat"></div>
  </div>
</main>

<script>
const champDocument = document.getElementById('document');
const champQuestion = document.getElementById('question');
const boutonInterroger = document.getElementById('interroger');
const zoneResultat = document.getElementById('resultat');

async function interroger() {
  const document_ = champDocument.value.trim();
  const question = champQuestion.value.trim();
  if (!document_ || !question) return;

  boutonInterroger.disabled = true;
  zoneResultat.innerHTML = '<div class="message systeme">Recherche des passages pertinents puis interrogation de Claude…</div>';

  try {
    const reponse = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ document: document_, question }),
    });
    const donnees = await reponse.json();

    if (donnees.erreur) {
      zoneResultat.innerHTML = '<div class="message systeme">Erreur : ' + donnees.erreur + '</div>';
      return;
    }

    let html = '<h3 style="font-size:0.95rem; color:var(--texte-doux);">Passages retenus (extraits envoyés à Claude) :</h3>';
    donnees.chunks.forEach((c) => {
      html += `<div class="chunk"><span class="score">score ${c.score}</span> — ${c.retenu ? '✅ retenu' : '✗ ignoré'}<br>${c.texte}</div>`;
    });
    html += '<h3 style="font-size:0.95rem; color:var(--texte-doux); margin-top:18px;">Réponse de Claude :</h3>';
    html += '<div class="message assistant">' + donnees.reponse + '</div>';
    zoneResultat.innerHTML = html;
  } catch (e) {
    zoneResultat.innerHTML = '<div class="message systeme">Erreur réseau : ' + e.message + '</div>';
  } finally {
    boutonInterroger.disabled = false;
  }
}

boutonInterroger.addEventListener('click', interroger);
</script>

<?php require __DIR__ . '/../includes/pied.php'; ?>
