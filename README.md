# Little Canary für Contao

> Ein kleiner Vogel, der dir sagt, ob alles läuft.

Minimales Demo-Theme für **Contao 5.7**. Nach der Installation zeigt eine einzige Startseite, ob Layout, Stylesheet, Schrift und Inhaltsausgabe funktionieren – ohne Abhängigkeiten, ohne Build-Schritt. Optional legt ein Konsolenbefehl eine versteckte Testseite mit allen Inhaltselementen an.

## Installation

**Contao Manager:** Paket `sascha-mueller/contao-little-canary` suchen, installieren und anschließend die Datenbank aktualisieren.

**Composer:**

```bash
composer require sascha-mueller/contao-little-canary
vendor/bin/contao-console contao:migrate
```

Die Migration legt an:

- Theme **Little Canary** mit Layout **Standard** (Typ *modern*, Template `page/layout/little-canary`) und den Modulen „Header – Logo“, „Hauptmenü“ und „Footer“.
- **Nur in einer Installation ohne Seiten** zusätzlich Startpunkt und Startseite mit einem Text-Element. Bestehende Seitenbäume bleiben unverändert – dort das Layout im Backend einem Startpunkt zuweisen.

## Testseite mit allen Inhaltselementen

```bash
vendor/bin/contao-console little-canary:test-page [--root=<ID>]
```

Legt die im Menü versteckte Seite `content-elements` (`noindex`) an – mit allen Inhaltselementen von Contao 5.7, einem Testformular, einem Testmodul und Testmedien in `files/little-canary-test/`.

## Aufbau

| Pfad | Inhalt |
| --- | --- |
| `contao/templates/page/layout/little-canary.html.twig` | Variante von `@Contao/page/layout`: ergänzt nur Stylesheets, Font-Preload und Favicon. Die Slots `header`, `main`, `footer` stammen unverändert aus dem Core. |
| `public/css/tokens.css` | Design-Tokens (`--lc-…`), Hell/Dunkel über `prefers-color-scheme` |
| `public/css/little-canary.css` | Grundlayout und Styles für alle Inhaltselemente, ausgerichtet auf das unveränderte Core-Markup |
| `public/fonts/` | Outfit 400/600/700, lokal gehostet (SIL OFL 1.1) |
| `public/img/` | Logo, Bildmarken, Favicon |

Contao-Templates werden nicht überschrieben.

## Tipps

- **Button-Optik für Hyperlinks:** im Feld *CSS-Klasse* des Elements `lc-button` (primär) oder `lc-button lc-button--secondary` eintragen.
- **Barrierefreiheit:** Alle Farbpaare erfüllen WCAG 2.2 AA in Hell und Dunkel; sanftes Scrollen nur ohne `prefers-reduced-motion`.

## Lizenz

Copyright © 2026 Sascha Müller

Dieses Paket steht unter der **GNU Lesser General Public License, Version 3 oder später** (LGPL-3.0-or-later), siehe [`LICENSE`](LICENSE) und die zugrunde liegende GPL-3.0 in [`COPYING`](COPYING).

**Zusätzliche Bedingungen nach Abschnitt 7 (c) und (e) der GPL-3.0:** Die Lizenz gewährt keine Rechte am Namen „Little Canary“ als Marken- oder Kennzeichenrecht. Abgeänderte Versionen dürfen nicht unter diesem Namen verbreitet werden, ohne deutlich als abgeändert gekennzeichnet zu sein.

Die Schrift **Outfit** (`public/fonts/`) steht unter der SIL Open Font License 1.1, siehe [`public/fonts/OFL.txt`](public/fonts/OFL.txt).
