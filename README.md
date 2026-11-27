# Kontingentkalkulator

WordPress-plugin med Flatsome UX Builder-elementer som regner ut reell fagforeningskontingent ut fra månedslønn eller timelønn, inkludert skattefradrag og valgfrie forsikringer. Inneholder også en fordelskalkulator der besøkende huker av fordeler og ser årlig sparing.

**Versjon:** 1.1.0  
**Forfatter:** Benjamin Pedersen  
**Dokumentasjon:** [wordpress-plugins-docs](https://wordpress-plugins-docs.vercel.app/plugins/kontingentkalkulator)

## Krav

- WordPress 6.6 eller nyere
- PHP 8.1 eller nyere
- Flatsome (UX Builder-elementene)

## Installasjon

1. Last ned release-zip fra [Releases](https://github.com/bli-synlig-plugins/kontingentkalkulator/releases), eller klon dette repoet.
2. Legg mappen `kontingentkalkulator` i `wp-content/plugins/`.
3. Aktiver **Kontingentkalkulator** under **Plugins**.

## Admin

Gå til **Kontingentkalkulator** i wp-admin. Satser, tekster, forsikringer og fordeler gjelder alle kalkulatorer. Farger og standardverdier settes på elementet i UX Builder.

- **Satser:** kontingentsats, skattefradragssats og maks fradragsgrunnlag per år.
- **Forsikringer:** legg til, rediger, vis/skjul og slett. Aktive forsikringer trekkes fra reell månedlig kostnad og vises som én rad i resultatet. De gir ikke skattefradrag.
- **Fordeler:** hovedkategorier (for eksempel Forsikringer) med fordeler inni. Hver fordel har årlig beløp, beskrivelse bak (i), om den skal være huket av som standard, og valgfri eksklusiv gruppe (fordeler med samme gruppenavn utelukker hverandre). Kategorien kan ha beskrivelse og valgfri lenke.
- **Tekster:** overskrifter, feltetiketter og suffikser, gruppert som på nettsiden. Inkluderer overskrift og valuta for fordelskalkulatoren.
- **Forklaringer:** tekst bak (i)-knappene. `{sats}`, `{fradrag}` og `{arsgrense}` erstattes med verdiene under Satser.

Forhåndsvisningen til høyre viser lønn over resultatet. Den oppdateres mens du skriver, og regner først ut når lønnen er større enn 0. Under **Fordeler** byttes forhåndsvisningen til fordelskalkulatoren.

## UX Builder

Elementene ligger under kategorien **Kontingentkalkulator**:

| Element | Beskrivelse |
|---------|-------------|
| **Kontingentkalkulator** | Container. Legg inn input og resultat som barn. |
| **Kalkulator: input** | Lønn (måned eller time) og timer per uke. |
| **Kalkulator: resultat** | Reell kostnad, rader og forklaringer. |
| **Fordelskalkulator** | Viser kategorier og fordeler fra admin. Besøkende huker av og ser årlig sparing. |

Bruk forhåndsvalget **Standard** på kontingentkalkulatoren for å sette inn container, input og resultat. Fordelskalkulatoren settes inn som eget element. Farger for header, bakgrunn og bryter settes på elementet. Lenker følger sidens egen styling og får et chevron-ikon.

## Lisens

GPLv2 or later.
