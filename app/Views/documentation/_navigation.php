<?php /** @var array<int,array<string,mixed>> $documentationGuides */ ?>
<nav class="docs-guide-nav" aria-label="Documentation sections">
    <a href="<?= e(url(ltrim($documentationBase, '/'))) ?>">All guides</a>
    <?php foreach ($documentationGuides as $navigationGuide): ?>
        <a href="<?= e(url(ltrim($documentationBase . '/' . $navigationGuide['slug'], '/'))) ?>"><?= $this->e((string) $navigationGuide['title']) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url(ltrim($documentationBase . '/whats-new', '/'))) ?>">What's new</a>
</nav>
<span class="docs-guide-nav-hint" aria-hidden="true">Swipe for more guides →</span>
