<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/outils.php';
$titre = 'Demo 5 — Agent multi-étapes';
require __DIR__ . '/../includes/entete.php';
?>

<main>
  <div class="conteneur">
    <div class="entete-page">
      <span class="etiquette">Démo 5 / 5</span>
      <h1>Agent multi-étapes</h1>
      <p>Même mécanique que la démo 4 (les outils), mais poussée en boucle : Claude peut enchaîner <strong>plusieurs</strong> appels d'outils, décider lui-même combien d'étapes lui sont nécessaires, avant de donner une réponse finale. C'est très exactement le principe derrière les agents comme Claude Code : <em>demander au modèle → agir → lui redonner le résultat → recommencer → jusqu'à ce qu'il juge avoir fini</em>.</p>
    </div>

    <div class="explication">
      <strong>La boucle d'un agent, en résumé :</strong>
      <pre style="background:#0a0b0e; border:1px solid var(--bordure); border-radius:8px; padding:12px; color:#9fe6a0; font-size:0.82rem; overflow-x:auto;">tant que Claude n'a pas fini :
    demander à Claude quoi faire ensuite
    si Claude demande un outil → l'exécuter, lui redonner le résultat
    sinon → c'est la réponse finale, on s'arrête</pre>
      Essaie une question qui demande plusieurs étapes : <em>"Compare la météo de Lyon et du Puy-en-Velay, et dis-moi où il fait le plus chaud"</em> — Claude devra appeler l'outil météo deux fois avant de pouvoir répondre.
    </div>

    <div class="chat">
      <div class="chat-messages" id="messages">
        <div class="message systeme">Pose une question qui demande plusieurs étapes.</div>
      </div>
      <div class="chat-saisie">
        <textarea id="saisie" rows="2" placeholder="Écris ton message…"></textarea>
        <button class="bouton" id="envoyer">Envoyer</button>
      </div>
    </div>
  </div>
</main>

<script>
const zoneMessages = document.getElementById('messages');
const champSaisie = document.getElementById('saisie');
const boutonEnvoyer = document.getElementById('envoyer');

let historique = [];

function ajouterMessage(role, texte) {
  const div = document.createElement('div');
  div.className = 'message ' + role;
  div.textContent = texte;
  zoneMessages.appendChild(div);
  zoneMessages.scrollTop = zoneMessages.scrollHeight;
  return div;
}

function afficherTrace(etapes) {
  if (!etapes.length) return;
  const conteneur = document.createElement('div');
  conteneur.style.margin = '4px 0 4px 12px';
  etapes.forEach((e) => {
    const div = document.createElement('div');
    div.className = 'etape-agent';
    div.innerHTML = `<span class="type">${e.type}</span><br>${e.detail}`;
    conteneur.appendChild(div);
  });
  zoneMessages.appendChild(conteneur);
  zoneMessages.scrollTop = zoneMessages.scrollHeight;
}

async function envoyer() {
  const texte = champSaisie.value.trim();
  if (!texte) return;

  ajouterMessage('utilisateur', texte);
  historique.push({ role: 'user', content: texte });
  champSaisie.value = '';
  boutonEnvoyer.disabled = true;

  const messageAttente = ajouterMessage('systeme', "L'agent réfléchit et enchaîne ses étapes…");

  try {
    const reponse = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ messages: historique }),
    });
    const donnees = await reponse.json();
    messageAttente.remove();

    if (donnees.erreur) {
      ajouterMessage('systeme', 'Erreur : ' + donnees.erreur);
    } else {
      afficherTrace(donnees.etapes);
      ajouterMessage('assistant', donnees.reponse);
      historique = donnees.historique;
    }
  } catch (e) {
    messageAttente.remove();
    ajouterMessage('systeme', 'Erreur réseau : ' + e.message);
  } finally {
    boutonEnvoyer.disabled = false;
    champSaisie.focus();
  }
}

boutonEnvoyer.addEventListener('click', envoyer);
champSaisie.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    envoyer();
  }
});
</script>

<?php require __DIR__ . '/../includes/pied.php'; ?>
