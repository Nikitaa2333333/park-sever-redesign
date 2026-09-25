<?php
/** @var array $doc */
$visual = [
    ['icon' => 'dog', 'title' => 'Собаки — только на улице'],
    ['icon' => 'prohibit', 'title' => 'К оленям — только с администратором'],
    ['icon' => 'bread', 'title' => 'Не кормим животных'],
    ['icon' => 'flame', 'title' => 'Огонь — только в мангальной зоне'],
];
?>
<div class="rules">
  <?php foreach ($doc['rules'] as $i => $r): $vz = $visual[$i] ?? ['icon' => 'leaf', 'title' => $r['title']]; ?>
    <article class="rule">
      <div class="rule__body">
        <p class="rule__head"><?= icon($vz['icon']) ?><span><?= h($vz['title']) ?></span></p>
        <p class="rule__text"><?= h($r['text']) ?></p>
      </div>
    </article>
  <?php endforeach; ?>
</div>
