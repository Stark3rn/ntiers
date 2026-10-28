<h1>Blagues</h1>

<?php if ($serviceDown): ?>
  <p class="errors">Le service est temporairement indisponible, réessaie dans un instant.</p>
<?php endif; ?>

<?php if ($errors): ?>
  <ul class="errors">
    <?php foreach ($errors as $error): ?>
      <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="/blagues" class="joke-form">
  <textarea name="text" maxlength="500" placeholder="Ta meilleure blague de riz..." required></textarea>
  <input type="text" name="author" maxlength="50" placeholder="Ton pseudo (optionnel)">
  <button type="submit">Envoyer</button>
</form>

<ul class="jokes-list">
  <?php foreach ($jokes as $joke): ?>
    <li>
      <p><?= nl2br(htmlspecialchars((string) ($joke['text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
      <span class="author">— <?= htmlspecialchars((string) ($joke['author'] ?? 'Anonyme'), ENT_QUOTES, 'UTF-8') ?></span>
    </li>
  <?php endforeach; ?>
  <?php if (!$jokes && !$serviceDown): ?>
    <li>Aucune blague pour l'instant. Sois le premier à en poster une !</li>
  <?php endif; ?>
</ul>
