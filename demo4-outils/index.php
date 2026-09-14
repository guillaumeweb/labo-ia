<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/outils.php';
$titre = 'Demo 4 — Function calling';
require __DIR__ . '/../includes/entete.php';
?>

<main>
  <div class="conteneur">
    <div class="entete-page">
      <span class="etiquette">Démo 4 / 5</span>
      <h1>Function calling (outils)</h1>
      <p>Jusqu'ici, Claude ne faisait que répondre en texte. Avec le <em>function calling</em>, on lui décrit des outils disponibles (ex: "consulter la météo"), et Claude peut demander qu'on les exécute pour lui — il ne les exécute jamais lui-même, c'est notre code qui le fait, puis on lui redonne le résultat pour qu'il termine sa réponse.</p>
    </div>

    <div class="explication">
      <strong>Deux outils disponibles ici :</strong> <code>meteo_actuelle(ville)</code> et <code>calculer(expression)</code>.<br>
      <strong>Comment ça marche :</strong>
      <ol>
        <li>On envoie ta question à Claude, avec la liste des outils qu'il peut demander à utiliser.</li>
        <li>Si Claude estime avoir besoin d'un outil, il répond non pas avec du texte mais avec une demande structurée (ex: <code>meteo_actuelle({ville: "Lyon"})</code>).</li>
        <li>Notre PHP exécute réellement cet appel (ici : interroge <a href="https://wttr.in" target="_blank" rel="noopener">wttr.in</a>) et renvoie le résultat à Claude.</li>
        <li>Claude rédige sa réponse finale en tenant compte de ce résultat.</li>
      </ol>
      Essaie : <em>"Quel temps fait-il au Puy-en-Velay ?"</em> ou <em>"Combien font 34 * 12 ?"</em>
    </div>

    <div class="chat">
      <div class="chat-messages" id="messages">
        <div class="message systeme">Pose une question qui nécessite la météo ou un calcul.</div>
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

function ajouterEtapes(etapes) {
  if (!etapes.length) return;
  const details = document.createElement('details');
  details.className = 'coulisses';
  details.innerHTML = '<summary>Voir les ' + etapes.length + ' étape(s) d\'outil</summary>';
  etapes.forEach((e) => {
    const div = document.createElement('div');
    div.className = 'etape-agent';
    div.innerHTML = `<span class="type">${e.type}</span><br>${e.detail}`;
    details.appendChild(div);
  });
  zoneMessages.appendChild(details);
}

async function envoyer() {
  const texte = champSaisie.value.trim();
  if (!texte) return;

  ajouterMessage('utilisateur', texte);
  historique.push({ role: 'user', content: texte });
  champSaisie.value = '';
  boutonEnvoyer.disabled = true;

  try {
    const reponse = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ messages: historique }),
    });
    const donnees = await reponse.json();

    if (donnees.erreur) {
      ajouterMessage('systeme', 'Erreur : ' + donnees.erreur);
    } else {
      ajouterEtapes(donnees.etapes);
      ajouterMessage('assistant', donnees.reponse);
      historique = donnees.historique; // on garde l'historique complet renvoyé par le serveur (avec les blocs tool_use)
    }
  } catch (e) {
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
