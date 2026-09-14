<?php
define('LABO_IA_APP', true);
require __DIR__ . '/../lib/claude.php';
$titre = 'Demo 2 — Streaming';
require __DIR__ . '/../includes/entete.php';
?>

<main>
  <div class="conteneur">
    <div class="entete-page">
      <span class="etiquette">Démo 2 / 5</span>
      <h1>Streaming</h1>
      <p>Même principe que la démo 1, mais la réponse arrive <strong>petit bout par petit bout</strong> au lieu d'attendre le texte entier — exactement comme dans l'interface de Claude. Utile dès que les réponses sont longues : l'utilisateur voit tout de suite que "ça travaille".</p>
    </div>

    <div class="explication">
      <strong>Comment ça marche :</strong>
      <ol>
        <li>Le navigateur ouvre une connexion à <code>api.php</code> et la garde ouverte.</li>
        <li><code>api.php</code> demande à l'API Claude une réponse "stream" : Claude renvoie le texte en petits fragments (des évènements <em>Server-Sent Events</em>), au fur et à mesure qu'il le génère.</li>
        <li>Dès qu'un fragment arrive, <code>api.php</code> le retransmet immédiatement au navigateur (sans attendre la fin).</li>
        <li>Le JavaScript ajoute chaque fragment au message affiché, d'où l'effet "machine à écrire".</li>
      </ol>
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
        <div class="message systeme">Pose une question pour voir la réponse arriver en direct.</div>
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
const champModele = document.getElementById('modele');

let historique = [];

function ajouterMessage(role, texte) {
  const div = document.createElement('div');
  div.className = 'message ' + role;
  div.textContent = texte;
  zoneMessages.appendChild(div);
  zoneMessages.scrollTop = zoneMessages.scrollHeight;
  return div;
}

async function envoyer() {
  const texte = champSaisie.value.trim();
  if (!texte) return;

  ajouterMessage('utilisateur', texte);
  historique.push({ role: 'user', content: texte });
  champSaisie.value = '';
  boutonEnvoyer.disabled = true;

  const divAssistant = ajouterMessage('assistant', '');
  let texteComplet = '';

  try {
    const reponse = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ messages: historique, model: champModele.value }),
    });

    const lecteur = reponse.body.getReader();
    const decodeur = new TextDecoder();
    let tampon = '';

    while (true) {
      const { done, value } = await lecteur.read();
      if (done) break;
      tampon += decodeur.decode(value, { stream: true });

      // Les évènements SSE sont séparés par une ligne vide.
      let indexSeparateur;
      while ((indexSeparateur = tampon.indexOf('\n\n')) !== -1) {
        const evenement = tampon.slice(0, indexSeparateur);
        tampon = tampon.slice(indexSeparateur + 2);
        if (evenement.startsWith('data: ')) {
          const fragment = JSON.parse(evenement.slice(6)).texte;
          texteComplet += fragment;
          divAssistant.textContent = texteComplet;
          zoneMessages.scrollTop = zoneMessages.scrollHeight;
        }
      }
    }
    historique.push({ role: 'assistant', content: texteComplet });
  } catch (e) {
    divAssistant.textContent = 'Erreur : ' + e.message;
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
