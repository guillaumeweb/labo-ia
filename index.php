<?php $titre = 'Labo IA — 5 architectures avec Claude'; include __DIR__ . '/includes/entete.php'; ?>

<main>
  <div class="conteneur">
    <div class="entete-page">
      <span class="etiquette">Projet perso</span>
      <h1>Labo IA</h1>
      <p>Cinq petites démos, cinq architectures différentes pour comprendre concrètement comment on construit avec Claude — du simple appel API jusqu'aux agents qui utilisent des outils. Chaque page montre aussi ce qui se passe "sous le capot" (requêtes, réponses, étapes).</p>
    </div>

    <div class="grille-demos">
      <a class="carte-demo" href="/demo1-chat/">
        <span class="numero">01</span>
        <h3>Chat simple</h3>
        <p>La brique de base : un message → l'API → une réponse. Tout part de là.</p>
      </a>
      <a class="carte-demo" href="/demo2-streaming/">
        <span class="numero">02</span>
        <h3>Streaming</h3>
        <p>La réponse s'affiche mot par mot, comme dans l'interface Claude, au lieu d'attendre le texte complet.</p>
      </a>
      <a class="carte-demo" href="/demo3-rag/">
        <span class="numero">03</span>
        <h3>RAG — augmenter le contexte</h3>
        <p>Comment une IA répond sur un document qu'elle n'a jamais appris, en n'envoyant que les passages pertinents.</p>
      </a>
      <a class="carte-demo" href="/demo4-outils/">
        <span class="numero">04</span>
        <h3>Function calling</h3>
        <p>Claude ne se contente pas de répondre en texte : il peut demander l'exécution d'un outil (ex. la météo).</p>
      </a>
      <a class="carte-demo" href="/demo5-agent/">
        <span class="numero">05</span>
        <h3>Agent multi-étapes</h3>
        <p>Plusieurs allers-retours enchaînés avec des outils jusqu'à une réponse finale — le principe derrière les agents comme Claude Code.</p>
      </a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/pied.php'; ?>
