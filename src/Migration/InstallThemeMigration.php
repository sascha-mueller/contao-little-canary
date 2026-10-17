<?php

declare(strict_types=1);

namespace LittleCanary\ContaoTheme\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Legt Theme "Little Canary", das Layout (Typ "modern") und die Module für die
 * Slots an. Startpunkt und Startseite entstehen nur, wenn es noch keine Seiten gibt;
 * bestehende Seitenbäume werden nicht verändert.
 */
class InstallThemeMigration extends AbstractMigration
{
    public const LAYOUT_TEMPLATE = 'page/layout/little-canary';

    private const TABLES = ['tl_theme', 'tl_layout', 'tl_module', 'tl_page', 'tl_article', 'tl_content'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Little Canary: Theme, Layout und Module anlegen';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(self::TABLES)) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_layout');

        if (!isset($columns['type'], $columns['template'], $columns['modules'])) {
            return false;
        }

        return !$this->connection->fetchOne('SELECT COUNT(*) FROM tl_layout WHERE template = ?', [self::LAYOUT_TEMPLATE]);
    }

    public function run(): MigrationResult
    {
        $time = time();
        $createPages = !$this->connection->fetchOne('SELECT COUNT(*) FROM tl_page');

        $theme = $this->insert('tl_theme', [
            'tstamp' => $time,
            'name' => 'Little Canary',
            'author' => 'Little Canary',
        ]);

        $brand = $this->insert('tl_module', [
            'pid' => $theme,
            'tstamp' => $time,
            'name' => 'Header – Logo',
            'type' => 'html',
            'html' => '<a class="lc-brand" href="{{env::base}}" aria-label="Little Canary – Startseite">
  <img class="lc-mark--light" src="{{env::base}}bundles/littlecanary/img/little-canary-mark.svg" alt="" width="40" height="40">
  <img class="lc-mark--dark" src="{{env::base}}bundles/littlecanary/img/little-canary-mark-dark.svg" alt="" width="40" height="40">
  <span class="lc-wordmark">little<span>canary</span></span>
</a>',
        ]);

        $navigation = $this->insert('tl_module', [
            'pid' => $theme,
            'tstamp' => $time,
            'name' => 'Hauptmenü',
            'type' => 'navigation',
            'ariaLabel' => 'Hauptmenü',
            'levelOffset' => 0,
            'showLevel' => 1,
            'navigationTpl' => 'nav_default',
        ]);

        $footer = $this->insert('tl_module', [
            'pid' => $theme,
            'tstamp' => $time,
            'name' => 'Footer',
            'type' => 'html',
            'html' => '<p><img src="{{env::base}}bundles/littlecanary/img/little-canary-favicon.svg" alt="" width="24" height="24"> Little Canary</p>',
        ]);

        $layout = $this->insert('tl_layout', [
            'pid' => $theme,
            'tstamp' => $time,
            'name' => 'Standard',
            'type' => 'modern',
            'template' => self::LAYOUT_TEMPLATE,
            'modules' => serialize([
                ['mod' => (string) $brand, 'col' => 'header', 'enable' => '1'],
                ['mod' => (string) $navigation, 'col' => 'header', 'enable' => '1'],
                ['mod' => '0', 'col' => 'main', 'enable' => '1'],
                ['mod' => (string) $footer, 'col' => 'footer', 'enable' => '1'],
            ]),
        ]);

        if (!$createPages) {
            return $this->createResult(true, 'Theme "Little Canary" angelegt. Das Layout "Standard" kann jetzt einem Startpunkt zugewiesen werden.');
        }

        $root = $this->insert('tl_page', [
            'pid' => 0,
            'sorting' => 128,
            'tstamp' => $time,
            'title' => 'Little Canary',
            'type' => 'root',
            'alias' => 'little-canary',
            'language' => 'de',
            'fallback' => 1,
            'includeLayout' => 1,
            'layout' => $layout,
            'published' => 1,
        ]);

        $start = $this->insert('tl_page', [
            'pid' => $root,
            'sorting' => 128,
            'tstamp' => $time,
            'title' => 'Startseite',
            'type' => 'regular',
            'alias' => 'index',
            'robots' => 'index,follow',
            'published' => 1,
        ]);

        $article = $this->insert('tl_article', [
            'pid' => $start,
            'sorting' => 128,
            'tstamp' => $time,
            'title' => 'Startseite',
            'alias' => 'startseite',
            'inColumn' => 'main',
            'published' => 1,
        ]);

        $this->insert('tl_content', [
            'pid' => $article,
            'ptable' => 'tl_article',
            'sorting' => 128,
            'tstamp' => $time,
            'type' => 'text',
            'headline' => serialize(['unit' => 'h1', 'value' => 'Läuft.']),
            'text' => '<p>Du kannst Deine Arbeiten nun ausführen und Little Canary wird Dir eine Plattform innerhalb von Contao dafür geben.</p>',
        ]);

        return $this->createResult(true, 'Theme "Little Canary" mit Startpunkt und Startseite angelegt.');
    }

    private function insert(string $table, array $row): int
    {
        $this->connection->insert($table, $row);

        return (int) $this->connection->lastInsertId();
    }
}
