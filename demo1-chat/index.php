<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';
$titre = 'Demo 1 — Chat simple';
require __DIR__ . '/../includes/entete.php';
?>

<main>
  <div class="conteneur">
    <div class="entete-page">
      <span class="etiquette">Démo 1 / 5</span>
      <h1>Chat simple</h1>
      <p>L'architecture la plus basique : ton message part vers l'API Claude, la réponse complète revient, s'affiche. Rien de plus — mais c'est la brique sur laquelle tout le reste (streaming, RAG, outils, agents) se construit.</p>
    </div>

    <div class="explication">
      <strong>Comment ça marche :</strong>
      <ol>
        <li>Le navigateur envoie ton message (+ l'historique de la conversation) à <code>api.php</code>, sur ton propre serveur.</li>
        <li><code>api.php</code> ajoute la clé API secrète et transmet tout à <code>api.anthropic.com/v1/messages</code>.</li>
        <li>Claude répond avec le texte complet, en un seul bloc.</li>
        <li><code>api.php</code> renvoie ce texte au navigateur, qui l'affiche.</li>
      </ol>
      La clé API ne quitte jamais le serveur : c'est pour ça qu'on passe par <code>api.php</code> plutôt que d'appeler l'API directement depuis le JavaScript du navigateur.
    </div>

    <div style="margin-bottom:16px;">
      <label for="modele" style="color:var(--texte-doux); font-size:0.85rem; margin-right:8px;">Modèle :</label>
      <select id="modele" class="champ-select">
        <?php foreach (claude_modeles_disponibles() as $id => $nom): ?>
          <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($nom) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="chat">
      <div class="chat-messages" id="messages">
        <div class="message systeme">Pose une question pour commencer.</div>
      </div>
      <div class="chat-saisie">
        <textarea id="saisie" rows="2" placeholder="Écris ton message…"></textarea>
        <button class="bouton" id="envoyer">Envoyer</button>
      </div>
    </div>

    <details class="coulisses">
      <summary>Voir la dernière requête / réponse brute (JSON)</summary>
      <pre id="brut">—</pre>
    </details>
  </div>
</main>

<script>
const zoneMessages = document.getElementById('messages');
const champSaisie = document.getElementById('saisie');
const boutonEnvoyer = document.getElementById('envoyer');
const champModele = document.getElementById('modele');
const zoneBrut = document.getElementById('brut');

let historique = []; // [{role: 'user'|'assistant', content: '...'}]

function ajouterMessage(role, texte) {
  const div = document.createElement('div');
  div.className = 'message ' + role;
  div.textContent = texte;
  zoneMessages.appendChild(div);
  zoneMessages.scrollTop = zoneMessages.scrollHeight;
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
      body: JSON.stringify({ messages: historique, model: champModele.value }),
    });
    const donnees = await reponse.json();

    if (donnees.erreur) {
      ajouterMessage('systeme', 'Erreur : ' + donnees.erreur);
    } else {
      ajouterMessage('assistant', donnees.reponse);
      historique.push({ role: 'assistant', content: donnees.reponse });
      zoneBrut.textContent = JSON.stringify(donnees.brut, null, 2);
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
