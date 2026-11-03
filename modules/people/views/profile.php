<?php
$h = [PeoplePublic::class, 'h'];
$portrait = PeopleSupport::imageUrl($person['profile_image']);
$originalPortrait = PeopleSupport::imageUrl($person['profile_image'], true);
$heroFacts = array_slice($facts, 0, 3); $otherFacts = array_slice($facts, 3);
$recordCount = count($relatedItems);
?>
<main id="archive-main" class="people-public bio-profile" tabindex="-1"><div id="main-content" class="bio-container" tabindex="-1">
    <nav class="bio-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="<?= SITE_URL ?>/people.php">People</a></li><li><?= PeoplePublic::icon('chevron-right') ?><span aria-current="page"><?= $h($person['name']) ?></span></li></ol></nav>
    <header class="bio-hero">
        <figure class="bio-portrait">
            <?php if ($portrait): ?><a class="bio-portrait-image" href="<?= $h($originalPortrait) ?>" target="_blank" rel="noopener" aria-label="View the original portrait of <?= $h($person['name']) ?>"><img src="<?= $h($portrait) ?>" alt="Portrait of <?= $h($person['name']) ?>" fetchpriority="high" width="720" height="900"></a><figcaption><a href="<?= $h($originalPortrait) ?>" target="_blank" rel="noopener">View original portrait <?= PeoplePublic::icon('arrows-pointing-out') ?></a></figcaption>
            <?php else: ?><div class="bio-portrait-image bio-initials" role="img" aria-label="Portrait not available for <?= $h($person['name']) ?>"><span aria-hidden="true"><?= $h(PeoplePublic::initials($person['name'])) ?></span></div><figcaption>Portrait not yet added</figcaption><?php endif; ?>
        </figure>
        <div class="bio-introduction">
            <a class="bio-back-link" href="<?= SITE_URL ?>/people.php">All biographies <?= PeoplePublic::icon('arrow-right') ?></a>
            <h1><?= $h($person['name']) ?></h1>
            <?php if (trim((string)$person['short_description']) !== ''): ?><p class="bio-summary"><?= $h($person['short_description']) ?></p><?php endif; ?>
            <?php if ($lifespan = PeoplePublic::lifespan($person)): ?><p class="bio-lifespan"><?= $h($lifespan) ?></p><?php endif; ?>
            <?php if ($heroFacts): ?><section class="bio-glance" aria-labelledby="bio-glance-title"><h2 id="bio-glance-title">At a glance</h2><dl><?php foreach ($heroFacts as $fact): ?><div><dt><?= $h($fact['label']) ?></dt><dd><?= $h($fact['value']) ?></dd></div><?php endforeach; ?></dl></section><?php endif; ?>
            <div class="bio-actions"><a class="bio-button bio-button-primary" href="#biography">Read biography <?= PeoplePublic::icon('arrow-right') ?></a><?php if ($relatedItems): ?><a class="bio-button" href="#collection">View collection <span class="bio-button-count"><?= $recordCount ?></span></a><?php endif; ?></div>
        </div>
    </header>
    <div class="bio-reading-layout">
        <article id="biography" class="bio-article" aria-labelledby="biography-title" data-bio-section><h2 id="biography-title">A life in context</h2><div class="bio-prose"><?php if ($paragraphs): foreach ($paragraphs as $paragraph): ?><p><?= nl2br($h($paragraph)) ?></p><?php endforeach; else: ?><p class="bio-muted">This biography is being researched. Explore the linked collection records while the profile is developed.</p><?php endif; ?></div></article>
        <aside class="bio-reading-aside" aria-label="Biography navigation and details"><div class="bio-aside-inner">
            <nav class="bio-page-nav" aria-label="On this page"><h2>On this page</h2><a href="#biography" aria-current="location">A life in context</a><?php if ($otherFacts): ?><a href="#profile-details">Profile details</a><?php endif; ?><a href="#collection">In the collection <span><?= $recordCount ?></span></a></nav>
            <?php if ($otherFacts): ?><section id="profile-details" class="bio-details" aria-labelledby="bio-details-title" data-bio-section><h2 id="bio-details-title">Profile details</h2><dl><?php foreach ($otherFacts as $fact): ?><div><dt><?= $h($fact['label']) ?></dt><dd><?= $h($fact['value']) ?></dd></div><?php endforeach; ?></dl></section><?php endif; ?>
        </div></aside>
    </div>
    <section id="collection" class="bio-collection" aria-labelledby="bio-collection-title" data-bio-section>
        <div class="bio-section-heading"><div><h2 id="bio-collection-title">In the collection</h2><p><?= $recordCount ? $recordCount . ' linked ' . ($recordCount === 1 ? 'record' : 'records') . ' connected to ' . $h($person['name']) . '.' : 'No public collection records have been linked to this biography yet.' ?></p></div><a class="bio-text-link" href="<?= SITE_URL ?>/search.php">Explore the archive <?= PeoplePublic::icon('arrow-right') ?></a></div>
        <?php if ($relatedItems): ?><div class="bio-record-grid"><?php foreach ($relatedItems as $item): $image = $item['image_path'] ? MediaProcessor::url($item['image_path'], 'display', 'image', $GLOBALS['storage'] ?? null) : ''; ?>
            <a class="bio-record" href="<?= SITE_URL ?>/item/<?= (int)$item['id'] ?>"><div class="bio-record-image"><?php if ($image): ?><img src="<?= $h($image) ?>" alt="<?= $h($item['title']) ?>" loading="lazy" width="640" height="440"><?php else: ?><span class="bio-no-image"><?= PeoplePublic::icon('document-text') ?><span>Image not yet added</span></span><?php endif; ?></div><div class="bio-record-caption"><span class="bio-record-number"><?= $h($item['reg_number']) ?></span><h3><?= $h($item['title']) ?></h3><?php if ($item['production_date']): ?><p><?= $h($item['production_date']) ?></p><?php endif; ?><span class="bio-record-action">View record <?= PeoplePublic::icon('arrow-right') ?></span></div></a>
        <?php endforeach; ?></div><?php endif; ?>
    </section>
    <div class="bio-return"><a href="<?= SITE_URL ?>/people.php">Browse more biographies <?= PeoplePublic::icon('arrow-right') ?></a><a href="#main-content">Back to top</a></div>
</div></main>
