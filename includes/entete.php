<?php
/**
 * En-tête HTML partagé. Variables attendues avant l'include :
 * $titre (string) — titre de l'onglet
 */
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titre ?? 'Labo IA') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="entete">
  <div class="conteneur">
    <a href="/" class="logo">Labo <span>IA</span></a>
    <nav>
      <a href="/">Démos</a>
      <a href="https://docs.anthropic.com/" target="_blank" rel="noopener">Docs Claude</a>
    </nav>
  </div>
</header>
