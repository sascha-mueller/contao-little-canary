<?php

declare(strict_types=1);

namespace LittleCanary\ContaoTheme\Command;

use Doctrine\DBAL\Connection;
use LittleCanary\ContaoTheme\Migration\InstallThemeMigration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Legt die im Menü versteckte Testseite "content-elements" mit allen
 * Inhaltselementen an (inkl. Testmedien, Formular und Modul).
 */
#[AsCommand(name: 'little-canary:test-page', description: 'Legt die Testseite "content-elements" mit allen Inhaltselementen an.')]
class CreateTestPageCommand extends Command
{
    private const MEDIA_DIR = 'files/little-canary-test';

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('root', null, InputOption::VALUE_REQUIRED, 'ID des Startpunkts (Standard: Startpunkt mit Little-Canary-Layout)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->connection->fetchOne("SELECT COUNT(*) FROM tl_page WHERE alias = 'content-elements'")) {
            $io->warning('Die Seite "content-elements" existiert bereits – nichts geändert.');

            return Command::FAILURE;
        }

        $theme = $this->connection->fetchOne('SELECT pid FROM tl_layout WHERE template = ?', [InstallThemeMigration::LAYOUT_TEMPLATE]);

        if (!$theme) {
            $io->error('Layout "'.InstallThemeMigration::LAYOUT_TEMPLATE.'" fehlt – zuerst die Datenbank migrieren (contao:migrate).');

            return Command::FAILURE;
        }

        $root = $input->getOption('root') ?? $this->connection->fetchOne(
            "SELECT p.id FROM tl_page p JOIN tl_layout l ON l.id = p.layout WHERE p.type = 'root' AND l.template = ? ORDER BY p.sorting",
            [InstallThemeMigration::LAYOUT_TEMPLATE],
        );

        if (!$root || 'root' !== $this->connection->fetchOne('SELECT type FROM tl_page WHERE id = ?', [$root])) {
            $io->error('Kein Startpunkt mit dem Little-Canary-Layout gefunden. Bitte --root=<ID> angeben.');

            return Command::FAILURE;
        }

        // Testmedien kopieren, öffentlich machen und in die Dateiverwaltung übernehmen
        $filesystem = new Filesystem();
        $target = $this->projectDir.'/'.self::MEDIA_DIR;
        $filesystem->mirror(\dirname(__DIR__, 2).'/resources/test-media', $target);
        $filesystem->touch($target.'/.public');

        foreach (['contao:symlinks', 'contao:filesync'] as $command) {
            $this->getApplication()->find($command)->run(new ArrayInput([]), $output->isVerbose() ? $output : new NullOutput());
        }

        $root = (int) $root;
        $theme = (int) $theme;
        $t = time();

        $this->connection->beginTransaction();

        $f = self::MEDIA_DIR.'/';
        $img = array_map(fn ($n) => $this->uuid("{$f}testbild-$n.png"), [1, 2, 3, 4]);
        $txt = $this->uuid("{$f}testdatei.txt");
        $wav = $this->uuid("{$f}testton-440hz.wav");
        $size = serialize(['', '', '']);

        // Seite (versteckt im Menü, nicht indexieren)
        $page = $this->insert('tl_page', [
            'pid' => $root, 'sorting' => 256, 'tstamp' => $t, 'title' => 'content-elements', 'type' => 'regular',
            'alias' => 'content-elements', 'robots' => 'noindex,nofollow', 'sitemap' => 'map_never',
            'hide' => 1, 'published' => 1,
        ]);

        // Hauptartikel
        $art = $this->insert('tl_article', [
            'pid' => $page, 'sorting' => 128, 'tstamp' => $t, 'title' => 'Inhaltselemente', 'alias' => 'inhaltselemente',
            'author' => 1, 'inColumn' => 'main', 'published' => 1,
        ]);

        // Quellartikel für "Artikel" und "Artikelteaser" – liegt im Footer-Slot, der keine Artikel ausgibt
        $srcArt = $this->insert('tl_article', [
            'pid' => $page, 'sorting' => 256, 'tstamp' => $t, 'title' => 'Eingebundener Artikel', 'alias' => 'eingebundener-artikel',
            'author' => 1, 'inColumn' => 'footer', 'published' => 1, 'showTeaser' => 1,
            'teaser' => '<p>Dies ist der Teasertext des eingebundenen Artikels. Der Link darunter führt zur Einzelansicht.</p>',
        ]);
        $this->insert('tl_content', [
            'pid' => $srcArt, 'ptable' => 'tl_article', 'sorting' => 128, 'tstamp' => $t, 'type' => 'text',
            'headline' => $this->hl('Eingebundener Artikel', 'h3'),
            'text' => '<p>Dieser Text stammt aus einem anderen Artikel und wird über das Element „Artikel“ eingebunden.</p>',
        ]);

        // Formular für das Element "Formular"
        $form = $this->insert('tl_form', [
            'tstamp' => $t, 'title' => 'Testformular', 'alias' => 'testformular', 'method' => 'POST', 'format' => 'raw',
        ]);
        $fs = 0;
        $opts = serialize([
            ['value' => 'a', 'label' => 'Option A'],
            ['value' => 'b', 'label' => 'Option B'],
            ['value' => 'c', 'label' => 'Option C'],
        ]);
        foreach ([
            ['type' => 'explanation', 'text' => '<p>Dieses Formular testet die Darstellung aller Formularfelder. Es speichert und versendet nichts.</p>'],
            ['type' => 'fieldsetStart', 'label' => 'Fieldset: Kontaktdaten'],
            ['type' => 'text', 'name' => 'name', 'label' => 'Textfeld', 'placeholder' => 'Platzhaltertext', 'mandatory' => 1],
            ['type' => 'text', 'name' => 'email', 'label' => 'E-Mail-Feld', 'rgxp' => 'email'],
            ['type' => 'password', 'name' => 'passwort', 'label' => 'Passwortfeld'],
            ['type' => 'fieldsetStop'],
            ['type' => 'textarea', 'name' => 'nachricht', 'label' => 'Textbereich', 'size' => serialize([4, 40])],
            ['type' => 'select', 'name' => 'auswahl', 'label' => 'Auswahlmenü', 'options' => $opts],
            ['type' => 'radio', 'name' => 'radio', 'label' => 'Radio-Buttons', 'options' => $opts],
            ['type' => 'checkbox', 'name' => 'checkbox', 'label' => 'Checkboxen', 'options' => $opts],
            ['type' => 'range', 'name' => 'bereich', 'label' => 'Bereichsregler', 'minval' => 0, 'maxval' => 10, 'step' => 1],
            ['type' => 'upload', 'name' => 'datei', 'label' => 'Datei-Upload', 'extensions' => 'txt,pdf'],
            ['type' => 'html', 'html' => '<p>HTML-Feld innerhalb des Formulars.</p>'],
            ['type' => 'hidden', 'name' => 'versteckt', 'value' => 'test'],
            ['type' => 'captcha', 'name' => 'captcha', 'label' => 'Sicherheitsfrage'],
            ['type' => 'submit', 'slabel' => 'Absenden'],
        ] as $field) {
            $this->insert('tl_form_field', ['pid' => $form, 'sorting' => $fs += 128, 'tstamp' => $t] + $field);
        }

        // Frontend-Modul für das Element "Modul"
        $module = $this->insert('tl_module', [
            'pid' => $theme, 'tstamp' => $t, 'name' => 'Testmodul (für Inhaltselement Modul)', 'type' => 'html',
            'html' => '<p>Dieser Absatz stammt aus dem Frontend-Modul „Testmodul“ und wird über das Element „Modul“ eingebunden.</p>',
        ]);

        // Inhaltselemente
        $sorting = 0;
        $ce = function (array $row, int $pid = 0, string $ptable = 'tl_article') use ($t, $art, &$sorting): int {
            return $this->insert('tl_content', [
                'pid' => $pid ?: $art, 'ptable' => $ptable, 'sorting' => $sorting += 128, 'tstamp' => $t,
            ] + $row);
        };

        $richText = '<p>Dies ist ein Absatz mit einem <a href="https://contao.org">Link</a>, <strong>fettem Text</strong>, <em>kursivem Text</em> und <code>inline code</code>.</p>
        <h3>Zwischenüberschrift H3</h3>
        <ul><li>Ungeordneter Listenpunkt 1</li><li>Ungeordneter Listenpunkt 2<ul><li>Verschachtelter Punkt</li></ul></li><li>Ungeordneter Listenpunkt 3</li></ul>
        <ol><li>Geordneter Listenpunkt 1</li><li>Geordneter Listenpunkt 2</li><li>Geordneter Listenpunkt 3</li></ol>
        <blockquote><p>Dies ist ein Zitat im Fließtext.</p></blockquote>
        <p>Dies ist ein letzter Absatz nach dem Zitat.</p>';

        $imageText = '<p>Dies ist ein Text-Element mit Bild. Der Text soll das Bild umfließen bzw. darüber oder darunter stehen, je nach gewählter Bildposition. Er ist bewusst etwas länger, damit das Umfließen auf breiten Bildschirmen sichtbar wird und auf schmalen Bildschirmen der Umbruch geprüft werden kann.</p>';

        // --- Texte ---
        foreach (range(1, 6) as $n) {
            $ce(['type' => 'headline', 'title' => "Überschrift H$n", 'headline' => $this->hl("Überschrift H$n", "h$n")]);
        }
        $textId = $ce(['type' => 'text', 'title' => 'Text – Formatierungen', 'headline' => $this->hl('Text – Formatierungen'), 'text' => $richText]);
        foreach (['above' => 'oben', 'left' => 'links', 'right' => 'rechts', 'below' => 'unten'] as $floating => $label) {
            $ce([
                'type' => 'text', 'title' => "Text – Bild $label", 'headline' => $this->hl("Text – Bild $label"), 'text' => $imageText,
                'addImage' => 1, 'singleSRC' => $img[0], 'size' => serialize(['400', '', 'proportional']), 'floating' => $floating,
                'overwriteMeta' => 1, 'alt' => 'Testbild 1', 'caption' => "Bildunterschrift (Bildposition: $label)",
            ]);
        }
        $ce(['type' => 'list', 'title' => 'Liste – ungeordnet', 'headline' => $this->hl('Liste – ungeordnet'), 'listtype' => 'unordered',
            'listitems' => serialize(['Ungeordneter Listenpunkt 1', 'Ungeordneter Listenpunkt 2', 'Ungeordneter Listenpunkt 3'])]);
        $ce(['type' => 'list', 'title' => 'Liste – geordnet', 'headline' => $this->hl('Liste – geordnet'), 'listtype' => 'ordered',
            'listitems' => serialize(['Geordneter Listenpunkt 1', 'Geordneter Listenpunkt 2', 'Geordneter Listenpunkt 3'])]);
        $ce(['type' => 'description_list', 'title' => 'Beschreibungsliste', 'headline' => $this->hl('Beschreibungsliste'),
            'data' => serialize([
                ['key' => 'Begriff 1', 'value' => 'Beschreibung des ersten Begriffs'],
                ['key' => 'Begriff 2', 'value' => 'Beschreibung des zweiten Begriffs'],
                ['key' => 'Begriff 3', 'value' => 'Beschreibung des dritten Begriffs'],
            ])]);
        $ce(['type' => 'table', 'title' => 'Tabelle', 'headline' => $this->hl('Tabelle'), 'summary' => 'Tabelle mit Kopf- und Fußzeile',
            'thead' => 1, 'tfoot' => 1, 'tableitems' => serialize([
                ['Spalte A', 'Spalte B', 'Spalte C'],
                ['Zeile 1, A', 'Zeile 1, B', 'Zeile 1, C'],
                ['Zeile 2, A', 'Zeile 2, B', 'Zeile 2, C'],
                ['Zeile 3, A', 'Zeile 3, B', 'Zeile 3, C'],
                ['Fußzeile A', 'Fußzeile B', 'Fußzeile C'],
            ])]);
        $ce(['type' => 'table', 'title' => 'Tabelle – sortierbar', 'headline' => $this->hl('Tabelle – sortierbar, erste Spalte als Kopf'),
            'thead' => 1, 'tleft' => 1, 'sortable' => 1, 'sortIndex' => 0, 'sortOrder' => 'ascending', 'tableitems' => serialize([
                ['Name', 'Wert', 'Einheit'],
                ['Gamma', '3', 'Stück'],
                ['Alpha', '1', 'Stück'],
                ['Beta', '2', 'Stück'],
            ])]);
        $ce(['type' => 'code', 'title' => 'Code', 'headline' => $this->hl('Code'), 'highlight' => 'php',
            'code' => "<?php\n\n// Kommentar: gibt eine Statusmeldung aus\nfunction status(string \$name): string\n{\n    return sprintf('%s läuft.', \$name);\n}\n\necho status('Little Canary');"]);
        $ce(['type' => 'markdown', 'title' => 'Markdown', 'headline' => $this->hl('Markdown'), 'markdownSource' => 'sourceText',
            'code' => "Dies ist ein **Markdown**-Absatz mit *Betonung*, `Code` und einem [Link](https://contao.org).\n\n- Markdown-Liste 1\n- Markdown-Liste 2\n\n> Markdown-Zitat"]);
        $ce(['type' => 'html', 'title' => 'HTML', 'headline' => $this->hl('HTML'),
            'html' => '<p>Dieser Absatz kommt aus dem Element <strong>HTML</strong> (gefiltert).</p>']);
        $ce(['type' => 'unfiltered_html', 'title' => 'Ungefiltertes HTML', 'headline' => $this->hl('Ungefiltertes HTML'),
            'unfilteredHtml' => '<p>Dieser Absatz kommt aus dem Element <strong>Ungefiltertes HTML</strong>.</p>']);
        $ce(['type' => 'template', 'title' => 'Template', 'headline' => $this->hl('Template'), 'customTpl' => 'content_element/template',
            'data' => serialize([
                ['key' => 'Schlüssel 1', 'value' => 'Wert 1'],
                ['key' => 'Schlüssel 2', 'value' => 'Wert 2'],
            ])]);

        // --- Links ---
        $ce(['type' => 'hyperlink', 'title' => 'Hyperlink', 'headline' => $this->hl('Hyperlink'), 'url' => 'https://contao.org',
            'linkTitle' => 'Textlink zu contao.org', 'target' => 1]);
        $ce(['type' => 'hyperlink', 'title' => 'Hyperlink – Button primär', 'headline' => $this->hl('Hyperlink – Button primär (CSS-Klasse lc-button)'),
            'url' => 'https://contao.org', 'linkTitle' => 'Primärer Button', 'cssID' => serialize(['', 'lc-button'])]);
        $ce(['type' => 'hyperlink', 'title' => 'Hyperlink – Button sekundär', 'headline' => $this->hl('Hyperlink – Button sekundär'),
            'url' => 'https://contao.org', 'linkTitle' => 'Sekundärer Button', 'cssID' => serialize(['', 'lc-button lc-button--secondary'])]);
        $ce(['type' => 'hyperlink', 'title' => 'Hyperlink – Bild', 'headline' => $this->hl('Hyperlink – Bildlink'), 'url' => 'https://contao.org',
            'linkTitle' => 'Bildlink zu contao.org', 'useImage' => 1, 'singleSRC' => $img[1], 'size' => serialize(['300', '', 'proportional']),
            'overwriteMeta' => 1, 'alt' => 'Testbild 2 als Link']);

        // --- Dateien ---
        $ce(['type' => 'image', 'title' => 'Bild', 'headline' => $this->hl('Bild'), 'singleSRC' => $img[2], 'size' => $size, 'fullsize' => 1,
            'overwriteMeta' => 1, 'alt' => 'Testbild 3', 'caption' => 'Bildunterschrift des Elements Bild']);
        $ce(['type' => 'gallery', 'title' => 'Galerie', 'headline' => $this->hl('Galerie (3 pro Reihe)'), 'multiSRC' => serialize($img),
            'sortBy' => 'name_asc', 'perRow' => 3, 'size' => serialize(['400', '300', 'crop']), 'fullsize' => 1]);
        $ce(['type' => 'download', 'title' => 'Download', 'headline' => $this->hl('Download'), 'singleSRC' => $txt, 'linkTitle' => 'Testdatei herunterladen']);
        $ce(['type' => 'downloads', 'title' => 'Downloads', 'headline' => $this->hl('Downloads'), 'multiSRC' => serialize([$txt, $img[0], $img[1], $wav]),
            'sortBy' => 'name_asc']);

        // --- Medien ---
        $ce(['type' => 'player', 'title' => 'Audio/Video', 'headline' => $this->hl('Audio/Video (Audiodatei)'), 'playerSRC' => serialize([$wav]),
            'playerCaption' => 'Testton 440 Hz, 2 Sekunden']);
        $ce(['type' => 'youtube', 'title' => 'YouTube', 'headline' => $this->hl('YouTube (mit Vorschaubild)'), 'youtube' => 'aqz-KE-bpKQ',
            'playerAspect' => '16:9', 'playerCaption' => 'Big Buck Bunny – lädt erst nach Klick von YouTube', 'splashImage' => 1, 'singleSRC' => $img[0], 'size' => $size]);
        $ce(['type' => 'vimeo', 'title' => 'Vimeo', 'headline' => $this->hl('Vimeo (mit Vorschaubild)'), 'vimeo' => '1084537',
            'playerAspect' => '16:9', 'playerCaption' => 'Big Buck Bunny – lädt erst nach Klick von Vimeo', 'splashImage' => 1, 'singleSRC' => $img[3], 'size' => $size]);

        // --- Elemente mit Kindelementen ---
        $acc = $ce(['type' => 'accordion', 'title' => 'Akkordeon', 'headline' => $this->hl('Akkordeon')]);
        foreach ([1, 2, 3] as $n) {
            $ce(['type' => 'text', 'sectionHeadline' => $this->hl("Akkordeon-Abschnitt $n", 'h3'),
                'text' => "<p>Inhalt des Akkordeon-Abschnitts $n.</p>"], $acc, 'tl_content');
        }
        $grp = $ce(['type' => 'element_group', 'title' => 'Elementgruppe', 'headline' => $this->hl('Elementgruppe')]);
        $ce(['type' => 'text', 'text' => '<p>Erstes Element innerhalb der Elementgruppe.</p>'], $grp, 'tl_content');
        $ce(['type' => 'text', 'text' => '<p>Zweites Element innerhalb der Elementgruppe.</p>'], $grp, 'tl_content');
        $sw = $ce(['type' => 'swiper', 'title' => 'Content-Slider', 'headline' => $this->hl('Content-Slider'), 'sliderDelay' => 0, 'sliderSpeed' => 300]);
        foreach ([0, 1, 2] as $i) {
            $ce(['type' => 'image', 'singleSRC' => $img[$i], 'size' => $size, 'overwriteMeta' => 1, 'alt' => 'Slide ' . ($i + 1)], $sw, 'tl_content');
        }

        // --- Einbindungen ---
        $ce(['type' => 'article', 'title' => 'Artikel', 'articleAlias' => $srcArt]);
        $ce(['type' => 'teaser', 'title' => 'Artikelteaser', 'article' => $srcArt]);
        $ce(['type' => 'alias', 'title' => 'Inhaltselement (Alias)', 'cteAlias' => $textId]);
        $ce(['type' => 'form', 'title' => 'Formular', 'form' => $form]);
        $ce(['type' => 'module', 'title' => 'Modul', 'module' => $module]);
        $ce(['type' => 'comments', 'title' => 'Kommentare', 'com_order' => 'ascending', 'com_template' => 'com_default', 'com_moderate' => 1]);

        // --- Benutzer (sichtbar je nach Login-Status) ---
        $ce(['type' => 'login', 'title' => 'Login', 'headline' => $this->hl('Login')]);
        $ce(['type' => 'two_factor', 'title' => 'Zwei-Faktor', 'headline' => $this->hl('Zwei-Faktor-Authentifizierung (nur eingeloggt)')]);
        $ce(['type' => 'manage_passkeys', 'title' => 'Passkeys', 'headline' => $this->hl('Passkeys verwalten (nur eingeloggt)')]);
        $ce(['type' => 'change_password', 'title' => 'Passwort ändern', 'headline' => $this->hl('Passwort ändern (nur eingeloggt)')]);
        $ce(['type' => 'close_account', 'title' => 'Konto schließen', 'headline' => $this->hl('Konto schließen (nur eingeloggt)')]);

        // --- Abschluss ---
        $ce(['type' => 'toplink', 'title' => 'Nach oben', 'linkTitle' => 'Nach oben']);

        $this->connection->commit();

        $io->success(\sprintf('Testseite "content-elements" (ID %d) mit %d Inhaltselementen angelegt.', $page, $sorting / 128));

        return Command::SUCCESS;
    }

    private function insert(string $table, array $row): int
    {
        $this->connection->insert($table, $row);

        return (int) $this->connection->lastInsertId();
    }

    private function uuid(string $path): string
    {
        return $this->connection->fetchOne('SELECT uuid FROM tl_files WHERE path = ?', [$path])
            ?: throw new \RuntimeException("Datei fehlt in der Dateiverwaltung: $path");
    }

    private function hl(string $text, string $unit = 'h2'): string
    {
        return serialize(['unit' => $unit, 'value' => $text]);
    }
}
